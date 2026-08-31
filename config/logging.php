<?php

use App\Logging\IstekBaglami;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Satırı hangi süreç yazdı (B6.3)
    |--------------------------------------------------------------------------
    |
    | `web` · `worker` · `scheduler` — `docker-compose.yml`'de her servise
    | ayrı yazılıyor ve günlük satırına bağlam olarak giriyor.
    |
    | ⚠️ BURADA, doğrudan `env()` ile DEĞİL. Yapılandırma önbelleğe
    | alındığında (`config:cache`) `env()` **null** döner; etiket sessizce
    | kaybolur ve "kuyruk işçisi öldü" alarmı hiç ateşlenmez — üstelik
    | hata vermeden.
    */
    'surec' => env('SUREC'),

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            /*
            | ★ HER SATIRA "BU HANGİ İSTEKTİ" BAĞLAMI. Ölçüldü: günlükteki
            | gerçek hatalar hangi markaya, hangi müşteriye ve hangi
            | isteğe ait olduğunu SÖYLEMİYORDU — teşhis edilemiyorlardı.
            */
            'tap' => [IstekBaglami::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            /*
            | ★ HER SATIRA "BU HANGİ İSTEKTİ" BAĞLAMI. Ölçüldü: günlükteki
            | gerçek hatalar hangi markaya, hangi müşteriye ve hangi
            | isteğe ait olduğunu SÖYLEMİYORDU — teşhis edilemiyorlardı.
            */
            'tap' => [IstekBaglami::class],
        ],

        /*
        |----------------------------------------------------------------
        | MAKİNE İÇİN GÜNLÜK — toplayıcı bunu okuyor (B6)
        |----------------------------------------------------------------
        |
        | ★ `daily` insan için, bu makine için. İkisi birden yazılıyor:
        | yerelde hata ayıklarken okunabilir satır lazım, toplayıcıya ise
        | ayrıştırılabilir alan.
        |
        | ⚠️ `driver: monolog` KULLANILIYOR — `daily` sürücüsünde
        | `formatter` anahtarı ÇALIŞMIYOR (Laravel onu yalnızca monolog
        | sürücüsünde okuyor). `daily` yazılıp `formatter` eklenseydi ayar
        | sessizce yok sayılır, dosya satır biçiminde yazılır ve toplayıcı
        | hiçbir alan çıkaramazdı.
        |
        | ⚠️ AYRI KLASÖR (`logs/json/`): toplayıcı `logs/*.log` desenini
        | okusaydı insan günlüğünü de çeker ve her satır iki kez
        | toplanırdı.
        |
        | ⚠️ `maxFiles` insan günlüğüyle AYNI (14): biri döner öteki
        | dönmezse 72 MB problemi yeni bir yerde geri gelir.
        */
        'json' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => RotatingFileHandler::class,
            'handler_with' => [
                'filename' => storage_path('logs/json/app.json'),
                'maxFiles' => (int) env('LOG_DAILY_DAYS', 14),
            ],
            'formatter' => JsonFormatter::class,
            'tap' => [IstekBaglami::class],
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
            'tap' => [IstekBaglami::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
