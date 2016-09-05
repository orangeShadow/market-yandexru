<?php namespace OrangeShadow\YandexMarket;

use DOMDocument;
use DOMElement;

/**
 * Class CreateXML
 * Подробнее на http://torg.mail.ru/info/122/#torg_price
 * @package OrangeShadow\TovariMailRu
 */
class CreateXML
{

    protected $dom;
    protected $shopElement;
    protected $xmlElements = [];
    protected $cleanFunction;
    protected $constructShopFlag = false;


    /**
     * CreateXML constructor.
     * Подробно о параметрах на https://yandex.ru/support/partnermarket/yml/about-yml.xml
     *
     * @param array $properties can have key: "name","company","url",...
     * @param $cleanFunction closure should return string
     *
     */
    function __construct($properties = [], $cleanFunction = null)
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        if (is_callable($cleanFunction)) {
            $this->cleanFunction = $cleanFunction;
        }

        if (!empty($properties)) {
            foreach ($properties as $key => $value) {
                $this->{$key}($value);
            }
        }


    }


    /**
     * Функция для запуска методов установки тегов по ключам массива через конструктор
     * @param $name
     * @param $parametrs
     */
    public function __call($name, $parametrs)
    {
        if (method_exists($this, 'set' . ucfirst(strtolower($name)))) {
            if (is_array($parametrs) && count($parametrs) > 1)
                $this->{'set' . ucfirst(strtolower($name))}($parametrs);
            else
                $this->{'set' . ucfirst(strtolower($name))}($parametrs[0]);
        } else {
            if (is_array($parametrs) && count($parametrs) == 1) {
                $this->setAnyValue($parametrs[0], $name);
            }
        }
    }

    protected function setAnyValue($value, $name)
    {
        if (!is_string($value))
            throw new \Exception('Ожидается строка, передано: ' . print_r($value, true));
        array_push($this->xmlElements, $this->dom->createElement($name, $this->clean($value, $name)));
    }


    /**
     * Устанавливаем название сайта
     * @param $value
     * @throws \Exception
     */
    public function setName($value)
    {
        if (!is_string($value))
            throw new \Exception('Ожидается строка, передано: ' . print_r($value, true));

        if (iconv_strlen($value, "UTF-8") > 20)
            throw new \Exception('Название не должно быть больше 20 символов: ' . print_r($value, true));

        array_push($this->xmlElements, $this->dom->createElement('name', $this->clean($value, 'name')));
    }

    /**
     * Название фирмы
     * @param $value
     * @throws \Exception
     */
    public function setCompany($value)
    {
        if (!is_string($value)) throw new \Exception('Ожидается строка, передано: ' . print_r($value, true));
        array_push($this->xmlElements, $this->dom->createElement('company', $this->clean($value, 'company')));
    }

    /**
     * Устанавливаем url сайта
     *
     * Кодируем строку в соответствии с RFC 3986, хотя необходим RFC 1738
     *
     * @param $value
     * @throws \Exception
     */
    public function setUrl($value)
    {
        if (!is_string($value)) throw new \Exception('Ожидается строка, передано: ' . print_r($value, true));

        $value = rawurlencode($value);

        array_push($this->xmlElements, $this->dom->createElement('url', $this->clean($value, 'url')));
    }


    /**
     * Указываем валюты магазина, ожидает приема массива с валютами id,rate,plus
     * @param $currencies
     * @throws \Exception
     */
    public function setCurrencies($currencies)
    {
        $currenciesElement = $this->dom->createElement('currencies');
        foreach ($currencies as $currency) {

            if (!isset($currency['id']))
                throw new \Exception('Неверно передан массив валют, отсутствует id или rate' . print_r($currency, true));

            $currencyElement = $this->dom->createElement('currency');
            foreach ($currency as $key => $value) {
                $currencyElement->setAttribute($key, $this->clean($value, "currency." . $key));
            }
            $currenciesElement->appendChild($currencyElement);
        }
        array_push($this->xmlElements, $currenciesElement);
    }


    /**
     * Создаем категории товаров
     * @param array $categories  содержит сущность(массив) с ключами: name,id,parentId
     * @throws \Exception
     */
    public function setCategories($categories)
    {
        $categoriesElement = $this->dom->createElement('categories');
        foreach ($categories as $category) {
            $errors = [];
            if (!isset($category['name'])) $errors[] = 'Каталог не содержит названия';
            if (!isset($category['id'])) $errors[] = 'Каталог не содержит id';

            if (isset($category['parent_id'])) {
                $category['parentId'] = $category['parent_id'];
                unset($category['parent_id']);
            }

            if (!empty($errors))
                throw new \Exception('Ошибка при создании каталога'
                    . print_r($errors, true)
                    . "\nПередан массив: "
                    . print_r($category, true));

            $categoryElement = $this->dom->createElement('category', $this->clean($category['name'], 'category.name'));
            unset($category["name"]);
            foreach ($category as $key => $value) {
                $categoryElement->setAttribute($key, $value);
            }
            $categoriesElement->appendChild($categoryElement);
        }
        array_push($this->xmlElements, $categoriesElement);
    }


    /**
     * Создание предложений
     * @param array $offers должен содержать массив сущностей: id,available,cbid,url,price,currencyId,categoryId,picture,typePrefix,vendor,model,description,delivery,pickup,local_delivery_cost
     * @throws \Exception
     */
    public function setOffers($offers)
    {
        $offersElement = $this->dom->createElement('offers');
        foreach ($offers as $offer) {

            if (!isset($offer['id']))
                throw new \Exception('Ошибка при создании торгового предложения, нет id элемента' . print_r($offer, true));

            if (!isset($offer['url']))
                throw new \Exception('Ошибка при создании торгового предложения, нет url' . print_r($offer, true));

            if (isset($offer['url']) && strlen($offer["url"])>512)
                throw new \Exception('Ошибка при создании торгового предложения, url больше 512 символов' . print_r($offer, true));

            if (!isset($offer['model']) && !isset($offer["name"]))
                throw new \Exception('Ошибка при создании торгового предложения, нет model(name)' . print_r($offer, true));

            if (!isset($offer['price']))
                throw new \Exception('Ошибка при создании торгового предложения, нет price' . print_r($offer, true));

            if (isset($offer['picture']) && strlen($offer["picture"])>512)
                throw new \Exception('Ошибка при создании торгового предложения, url картинки больше 512 символов' . print_r($offer, true));

            if (!isset($offer['currencyId']))
                throw new \Exception('Ошибка при создании торгового предложения, нет currencyId' . print_r($offer, true));

            if (!isset($offer['categoryId']))
                throw new \Exception('Ошибка при создании торгового предложения, нет categoryId' . print_r($offer, true));



            $offerElement = $this->dom->createElement('offer');

            $offerElement->setAttribute('id', $offer['id']);
            unset($offer['id']);

            if (isset($offer['available'])) {
                $offerElement->setAttribute('available', $offer['available'] ? "true" : "false");
                unset($offer['available']);
            }

            if (isset($offer['bid'])) {
                $offerElement->setAttribute('bid', $offer['bid']);
                unset($offer['bid']);
            }

            foreach ($offer as $key => $property) {


                if ($key == "price" && is_array($property)) {

                    //price может передаваться как массив ["value"=>1400.00,"from"=>true];
                    $propertyElement = $this->dom->createElement($key, $this->clean($property["value"], "offer." . $key));
                    $propertyElement->setAttribute('from',$property["from"]);

                }else{
                    $propertyElement = $this->dom->createElement($key, $this->clean($property, "offer." . $key));
                }

                $offerElement->appendChild($propertyElement);
            }
            $offersElement->appendChild($offerElement);
        }

        array_push($this->xmlElements, $offersElement);
    }

    public function setCleanFunction($function)
    {
        if (is_callable($function)) {
            $this->cleanFunction = $function;
        }
    }


    /**
     * Создание элемента Shop, который содержит описание магазина и его товарные предложения
     */
    public function constructShop()
    {
        if ($this->constructShopFlag) return $this->dom->getElementsByTagName('shop');

        $shopElement = $this->dom->createElement('shop');

        foreach ($this->xmlElements as $element) {
            $shopElement->appendChild($element);
        }
        $this->constructShopFlag = true;

        $this->dom->appendChild($shopElement);

        return $shopElement;
    }


    /**
     * Чистим код от html символов или используем функцию которую передал пользователь
     * @value значение
     * @key ключ
     */
    protected function clean($value, $key)
    {
        if (!empty($this->cleanFunction) && is_callable($this->cleanFunction)) {
            return call_user_func($this->cleanFunction, $value, $key);
        }

        if (empty($value)) return $value;

        if (!is_string($value) && !is_numeric($value))
            throw new \Exception('Ожидается строка, передан ' . gettype($value) . ' ' . print_r($value, true));

        return htmlspecialchars($value);
    }

    public function __toString()
    {
        header("Content-Type: text/xml");

        $dt = new \DateTime();
        $yml_catalog = $this->dom->createElement('yml_catalog');
        $yml_catalog->setAttribute('date', $dt->format('Y-m-d H:i'));

        $yml_catalog->appendChild($this->constructShop());

        $this->dom->appendChild($yml_catalog);

        return $this->dom->saveXML();
    }


}
