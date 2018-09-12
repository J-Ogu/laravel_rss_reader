<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\IntrospectionProcessor;

class AuthLog extends LogDriverAbstract
{
    /**
     * Create a custom Monolog instance.
     *
     * @param  array  $config config/logging.php で指定した authlog 以下のものを取得できる
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        // StreamHandler を生成
        $handler = $this->prepareHandler(
            new RotatingFileHandler(
                $config['path'], $config['days'] ?? 7, $this->level($config),
                $config['bubble'] ?? true, $config['permission'] ?? null, $config['locking'] ?? false
            )
        );

        // ログに出力するフォーマット
        $format = '[%datetime% %channel%.%level_name%] %message% [%context%] [ip:%extra.ip% agent:%extra.agent% hostname:%extra.hostname%]' . PHP_EOL;

        // StreamHandler にフォーマッタをセット
        $handler->setFormatter(
            tap(new LineFormatter($format, null, true, true), function ($formatter) {
            })
        );
        //extraフィールドにIPアドレスとユーザエージェントを追加
        $ip = new WebProcessor();
        //ユーザエージェントをextraフィールドに項目追加
        $ip->addExtraField("agent","HTTP_USER_AGENT");
        // 各ログハンドラにフォーマッタとプロセッサを設定
        $handler->pushProcessor($ip);
        //ホスト名をセット
        $handler->pushProcessor(function ($record){
            $record['extra']['hostname'] = gethostbyaddr($_SERVER['REMOTE_ADDR']);
            return $record;
        });

        // Monolog のインスタンスを生成して返す
        return new Logger($this->parseChannel($config), [
            new FingersCrossedHandler(
                $handler,
                $config['activation'] ?? null,
                0,
                true,
                true,
                $config['pass'] ?? null
            )
        ]);
    }
}