<?php
header('Access-Control-Allow-Origin: *');
define('STOP_STATISTICS', true);
define('PUBLIC_AJAX_MODE', true);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if (!in_array($_SERVER['REMOTE_ADDR'], HOST_1C) && $_SERVER['SERVER_NAME'] != 'dev2.autoscaners.ru') exit;

switch ($_REQUEST['action']) {
    case 'setBonuses':
        $uid = (int) $_REQUEST['USER'];
        $user = new CUser;
        $fields = Array(
            "UF_BONUSES" => (int)$_REQUEST['BONUSES'],
            "UF_PARTNER_ID" => $_REQUEST['PARTNER_ID']
        );
        echo $user->Update($uid, $fields) ? 'success' : 'error';
        break;
    case 'setPersonalDiscount':
        $uid = (int) $_REQUEST['USER'];
        $user = new CUser;
        $fields = Array(
            "UF_DISCOUNT" => (int)$_REQUEST['DISCOUNT']
        );
        echo $user->Update($uid, $fields) ? 'success' : 'error';
        break;
    case 'getUrl':
        $guid = trim($_REQUEST['GUID']);
        $url = 'error';
        if ($guid) {
            $rs = CIBlockElement::GetList([], ['XML_ID' => $guid], false, false, [
                'ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_PARENT_ELEMENT.ID', 'DETAIL_PAGE_URL', 'PROPERTY_PARENT_ELEMENT.DETAIL_PAGE_URL'
            ])->GetNextElement();
            if ($rs) {
                $url = ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $rs->GetFields()['DETAIL_PAGE_URL'];
            }
            echo $url;
        }
        break;
    case 'getCart':
        global $USER;
        $email = trim($_REQUEST['manager']);
        $asFile = isset($_REQUEST['file']);
        $result = false;
        $rs = CUser::GetList(($by = "NAME"), ($order = "desc"), ['EMAIL' => $email, 'GROUPS_ID' => [1, 5, 6, 11, 12]])->Fetch();
        if ($rs) {
            // HACK
            $res = $USER->Authorize($rs['ID']);
            if ($res) {
                // Получаем корзину юзера
                $dbBasketItems = CSaleBasket::GetList(
                    array(),
                    array("FUSER_ID" => CSaleBasket::GetBasketUserID(), "LID" => SITE_ID, "ORDER_ID" => "NULL"), false, false,
                    array("PRODUCT_ID", "QUANTITY", "PRODUCT_XML_ID")
                );
                while ($arItems = $dbBasketItems->Fetch()) {
                    $db_props = CIBlockElement::GetProperty(3, $arItems['PRODUCT_ID'], array(), Array("CODE" => "ARTNUMBER"));
                    while ($ar_props = $db_props->Fetch()) {
                        $arItems['ARTNUMBER'] = $ar_props["VALUE"];
                    }
                    $arBasketItems[] = $arItems;
                }
                //var_dump($arBasketItems);
                if (!empty($arBasketItems[0])) {
                    $content = "<?xml version='1.0' encoding='UTF-8'?><КоммерческаяИнформация ДатаФормирования=\"" . date(c) . "\">\n";
                    $content .= "<Менеджер>" . $email . "</Менеджер>\n";
                    $content .= "<Товары>\n";
                    foreach ($arBasketItems as $k => $v) {
                        $content .= "<Товар>\n<GUID>" . $v['PRODUCT_XML_ID'] . "</GUID>\n<Артикул>" . $v['ARTNUMBER'] . "</Артикул>\n<Количество>" . (int)$v['QUANTITY'] . "</Количество>\n</Товар>\n";
                    }
                    $content .= "</Товары></КоммерческаяИнформация>";
                    $result = true;

                    $file = $_SERVER['DOCUMENT_ROOT'] . "/upload/manager/" . $email . ".xml";
                    file_put_contents($file, $content);
                    if ($asFile) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename='.basename($file));
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($file));
                    }
                    echo $content;
                }
            }
            $USER->Logout();
        }
        if (!$result) echo 'error';
        break;
}
