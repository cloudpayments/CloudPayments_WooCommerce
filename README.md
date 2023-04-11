# CloudPayments module for WordPress - WooCommerce

Модуль позволит добавить на ваш сайт оплату банковскими картами через платежный сервис [CloudPayments](https://cloudpayments.ru/Docs/Connect). 
Для корректной работы модуля необходима регистрация в сервисе.
Порядок регистрации описан в [документации CloudPayments](https://cloudpayments.ru/Docs/Connect).

## Возможности:
* Одностадийная схема оплаты;
* Двухстадийная схема;
* Оплата в 1 клик;
* Информирование СMS о статусе платежа;
* Выбор языка виджета;
* Выбор дизайна виджета;
* Возможность указать назначение платежа;
* Поддержка WooCommerce Subscriptions;
* Поддержка онлайн-касс (ФЗ-54);
* Отправка чеков по email;
* Отправка чеков по SMS;
* Теги способа и предмета расчета; 
* Отдельный параметр НДС для доставки.

## Совместимость:

WordPress 4.9.7 и выше;  
WooCommerce 3.4.4 и выше.

## Установка

1. Скопируйте папку `cloudpayments-gateway-for-woocommerce` в директорию `wp-content/plugins/` на вашем сервере или установите плагин напрямую через раздел плагинов WordPress.

2. Зайдите в "Управление сайтом" -> "Плагины". Активируйте плагин "CloudPayments Gateway for WooCommerce".

3. В управлении сайтом зайдите в "WooCommerce" -> "Настройки" -> "Оплата" -> "CloudPayments". Отметьте галочкой  "Enable CloudPayments".
![CPsettings](pics/settings.png)

* **Включить/Выключить** - Включение/Отключение платежной системы;  
* **Включить DMS** - Включение двухстадийной схемы оплата платежа (холдирование);
* **Тест оплаты заказа** - Можете настроить текст назначения платежа;  
* **Статус для оплаченного заказа** - **Обработка** (Если не предусматривается другой функционал);  
* **Статус для отмененного заказа** - **Отменен** (Если не предусматривается другой функционал);  
* **Статус авторизованоого платежа DMS** - **На удержании** (Или **Платеж авторизован**, если предусматривается другой функционал);  
* **Наименование** - Заголовок, который видит пользователь в процессе оформления заказа;  
* **Описание** - Описание метода оплаты;  
* **Public_id** - Public id сайта из личного кабинета CloudPayments;  
* **Password for API** - API Secret из личного кабинета CloudPayments;  
* **Валюта магазина** - Российский рубль (Если не предусматривается использовать другие валюты);  
* **Дизайн виджета** - Выбор дизайна виджета из 3 возожных (classic, modern, mini);  
* **Язык виджета** - Русский МСК (Если не предусматривается использовать другие языки).   

Использовать функцуионал онлайн касс:
* **Включить/Выключить** - Включение/отключение формирования онлайн-чека при оплате;  
* **ИНН** - ИНН вашей организации или ИП, на который зарегистрирована касса;  
* **Ставка НДС** - Укажите ставку НДС товаров;  
* **Ставка НДС для доставки** - Укажите ставку НДС службы доставки;  
* **Система налогообложения организации** - Тип системы налогообложения;  
* **Способ расчета** - признак способа расчета;  
* **Предмет расчета** - признак предмета расчета;  
* **Действие со штрих-кодом** - отправление артикула товара в чек как шрих-код;
* **Статус которым пробивать 2ой чек при отгрузке товара или выполнении услуги** - **Выполнен** (Или **Доставлен**, если предусматривается другой функционал)  
_Согласно ФЗ-54 владельцы онлайн-касс должны формировать чеки для зачета и предоплаты. Отправка второго чека возможна только при следующих способах расчета: Предоплата, Предоплата 100%, Аванс._  

Нажмите "Сохранить изменения".

В личном кабинете CloudPayments зайдите в настройки сайта, пропишите в настройках уведомления, как описано на странице настройки модуля на указанный адрес:  
![webHooks](pics/Webhook.png)

Вы готовы принимать платежи с банковских карт с помощью CloudPayments!

== Frequently Asked Questions ==

= Какой порядок подключения? =

Для подключения к системе приема платежей CloudPayments, необходимо выполнить следующие действия:
* Оставить заявку.
* Получить ответ от персонального менеджера. Он будет сопровождать на всех этапах.
* Ознакомиться с преимуществами работы и списком необходимых документов.
* Договориться о коммерческих условиях с персональным менеджером.
* Получить доступ в личный кабинет. Он необходим для подписания договора и дальнейшей работы со всеми инструментариями CloudPayments.
* Выполнить техническую интеграцию сайта.
* Провести тестовые платежи. После успешных тестов — сообщить об этом менеджеру, который переведет сайт в боевой режим. 
 
= Как получить URL адрес с копией отправленного онлайн-чека в админ панели магазина? =

Для получения URL адреса копии отправленного онлайн-чека в комментариях к заказу необходимо прописать в личном кабинете CloudPayments адрес для уведомления. Для этого зайдите в настройки сайта, пропишите в настройках адрес: 

* **Receipt Уведомление** (Уведомление об онлайн-чеке):\
https://domen.ru/wc-api/wc_cloudpayments_gateway?action=receipt

Где domain.ru — доменное имя вашего сайта.

= Обновление для токенов =

Если у вас ранее была установлена Beta версия плагина, которая использовала токены, то для дальнейшего использования сохраненных токенов в новом плагине необходимо поправить значения в базе магазина: заменить в таблице 'wp_woocommerce_payment_tokens' в поле 'gateway_id' значение c 'cpgwwc' на 'wc_cloudpayments_gateway'.

= Обновление адресов уведомлений =

Если ранее пользовались предыдущими версиями модулья, то необходимо обновите адреса уведомлений в лк Cloudpayments. Новые можно увидеть в настройках метода оплаты

= Локализация надписей метода оплаты =

В файле /cloudpayments-gateway-for-woocommerce/payment-fields-checkout.php можно заменить локализацию текста табличек на нужный текст.

== Upgrade Notice ==  

= 3.0.9 =
* Исправлен баг с передачей данных для онлайн-чека при оплате по сохраненной карте

= 3.0.8 =
* Обновление документации

= 3.0.7 =
* Минорное исправления
* 
= 3.0.6 =
* Добавлен выбор валюты виджета
* Добавлены дополнительные параметры фискализации - [подробнее](https://static.cloudpayments.ru/docs/uz/CP_WooCommerce_UZ.pdf)

= 3.0.5 =
* Минорное исправления

= 3.0.4 =
* Минорное исправления
* Поправлен скрипт отображения уведомлений в настройках способа оплаты
* Добавлена поддержка мультивалютности
* Добавлена возможность выбора способов доставки, для которого будет работать способ оплаты
* Добавлена передача ID транзакции в Примечания заказа

= 3.0.3 =
* Исправлено опведение при отменен захолдированного платежа
* Добавили описане ошибки по неуспешной оплате по токену в заметки заказа

= 3.0.2 =
* Поправлен редирект при неуспещной оплате.

= 3.0.1 =
* Поправлен скрипт сохранения токена карты.

= 3.0 =
* Код плагина переписан в соответствии со стандартами WP;
* Добавлена оплата в 1 клик с помощью токенов;
* Поддержка WooCommerce Subscriptions;
* Обновлены адреса уведомлений.

= 2.0 =
* Добавлены теги способов и предметов расчета;
* Добавлен обработчик receipt уведомлений.

== Changelog ==

= 3.0.9 =
* Исправлен баг с передачей данных для онлайн-чека при оплате по сохраненной карте

= 3.0.8 =
* Обновление документации

= 3.0.7 =
* Минорное исправления

= 3.0.6 =
* Добавлен выбор валюты виджета
* Добавлены дополнительные параметры фискализации - [подробнее](https://static.cloudpayments.ru/docs/uz/CP_WooCommerce_UZ.pdf)

= 3.0.5 =
* Минорные исправления

= 3.0.4 =
* Добавлена поддержка мультивалютности. (выбор конкретной валюты платежа или валюты магазина).
* Добавлен выбор способа доставки, для которого будет включен способ оплаты
* Добавлена передача ID транзакции в Примечание заказа

= 3.0.3 =
* Исправлено опведение при отменен захолдированного платежа
* Добавили описане ошибки по неуспешной оплате по токену в заметки заказа

= 3.0.2 =
* Минорное исправления

= 3.0.1 =
* Минорное исправления

= 3.0 =
* Вовзращение плагина в маркетплейс;
* Код плагина переписан в соответствии со стандартами WP;
* Добавлена оплата в 1 клик с помощью токенов;
* Поддержка WooCommerce Subscriptions;

= 2.2.4 =
* минорные исправления

= 2.2.3 =
* минорные исправления

= 2.2.2 =
* Устранена ошибка fail уведомления.

= 2.2.1 =
* Устранены незначительные ошибки.

= 2.1 =
* Устранены некоторые ошибки в описании.

= 2.0 =
* Добавлен новый функционал.

= 1.0 =
* Размещение плагина в маркетплейс.

== Changelog == 
