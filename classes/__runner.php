<?php
/**
 * Created by PhpStorm.
 * User: LifarAV
 * Date: 17.07.14
 * Time: 14:57
 */

namespace Application;

class Runner1
{

    static private $connectionHandler;
    static private $config = [];

    function __construct($connectionHandler, array $config)
    {
        if ($connectionHandler === false || $config === false)
        {
            echo 'Error! Runner doesn\'t initialized.'
                . PHP_EOL;
            return false;
        }
        static::$connectionHandler = $connectionHandler;
        static::$config = $config;
    }

    /** Основной процесс, анализирующий сообщения и принимающий решения, что с ними делать
     *
     * Получаем все сообщения из исходной папки
     * Анализируем заголовки
     * В зависимости от них принимаем решение, что делать с письмом - вызываем методы и передаем в них ID сообщений
     *
     * @return void
     */
    public function execute()
    {
        echo '... See INBOX' . PHP_EOL;
        $messageCount = @\imap_num_msg(static::$connectionHandler);
        echo '... Messages: ' . $messageCount . PHP_EOL;

        // Парсим каждое сообщение в INBOX
        if ($messageCount > 0)
        {
            for ($i=1; $i<=$messageCount; ++$i)
            {
                $headers[$i] = @\imap_headerinfo(static::$connectionHandler, $i);

                $hostTo = $headers[$i]->to[0]->host;
                $hostFrom = $headers[$i]->from[0]->host;

                // Входящее внешнее письмо
                if ($hostTo == static::$config['targetDomain'] && $hostFrom != static::$config['targetDomain'])
                {
                    static::moveToBox($headers[$i], $i, 'Inbox');
                }
                // Исходящее внешнее письмо
                elseif ($hostTo != static::$config['targetDomain'] && $hostFrom == static::$config['targetDomain'])
                {
                    static::moveToBox($headers[$i], $i, 'Sent');
                }
                // Внутренняя переписка
                elseif ($hostTo == static::$config['targetDomain'] && $hostFrom == static::$config['targetDomain'])
                {
                    static::copyToBox($headers[$i]->to[0]->mailbox, $i, 'Inbox');
                    static::copyToBox($headers[$i]->from[0]->mailbox, $i, 'Sent');
                    static::removeMessage($i);
                }
                // Спам
                else
                {
                    static::moveToBox($headers[$i], $i, 'Junk');
                }

                $headers[$i] = null;
                unset($headers[$i]);
            }
        }
        // Если не подключились
        if (static::$connectionHandler === false)
        {
            echo 'Error! Can\'t execute aggregate.'
                . PHP_EOL;
        }
    }

    /**
     * Переносит сообщения в нужную папку
     *
     * @param string $messageObject Объект сообщения
     *
     * @param string $messageNumber Номер сообщения
     *
     * @param string $box Исходный путь папки
     *
     * @return void
     */
    private static function moveToBox($messageObject, $messageNumber, $box = 'Inbox')
    {
        // Получаем объект заголовков сообщения.
        // Смотрим название ящика, кому оправляется на домен host.com.
        // Если есть ящик, переносим письмо в него
        // Если ящика нет, создаем его, подписываемся, переносим письмо в него
        $fromArray = [
            'mailbox' => str_replace('.', '', $messageObject->from[0]->mailbox),
            'host' => $messageObject->from[0]->host,
        ];
        $toArray = [
            'mailbox' => str_replace('.', '', $messageObject->to[0]->mailbox),
            'host' => $messageObject->to[0]->host,
        ];
        $boxName = static::$config['host'] . 'INBOX.' . $toArray['mailbox'];
        $toCreateBox = 1;

        if ($box == 'Sent' && $toArray['host'] != static::$config['targetDomain']) {
            $toArray['mailbox'] = $fromArray['mailbox'];
            $toCreateBox = 0;
        }

        echo '... Move email from \''
            . $fromArray['mailbox']
            . '@'
            . $fromArray['host']
            . '\' to \''
            . $toArray['mailbox'] . '.'
            . $box
            . '\' box.' . PHP_EOL;

        $boxesArray = @\imap_list(static::$connectionHandler, static::$config['host'], '*');
        if (array_search($boxName . '.Inbox', $boxesArray))
        {
            echo '... Box \'' . $toArray['mailbox'] . '.Inbox' . '\' found.' . PHP_EOL;
        } else {
            if ($toCreateBox === 1) {
                // Если ящик не найден, создаем его и подписываемся
                echo '... Box \'' . $toArray['mailbox'] . '.Inbox' . '\' not found. Create box!' . PHP_EOL;
                static::createBoxCollection($boxName);
            }
        }

        echo '... Move email to \'' . $toArray['mailbox'] . '\' box.' . PHP_EOL;
        if (!@\imap_mail_move(static::$connectionHandler, $messageNumber, 'INBOX.' . $toArray['mailbox'] . '.' . $box))
        {
            echo '>>> Unknown move email error. Exiting!' . PHP_EOL;
        }
        echo '... Good Work! Proceed next email!' . PHP_EOL;
    }

    /**
     * Создает коллекцию папок для нужного пользователя
     *
     * @param string $boxName Путь и название папки пользователя
     *
     * @return void
     */
    private static function createBoxCollection($boxName)
    {
        $boxCollection = [
            'upper' => $boxName,
            'inbox' => $boxName . '.' . imap_utf7_encode('Inbox'),
            'sent' => $boxName . '.' . imap_utf7_encode('Sent'),
            'junk' => $boxName . '.' . imap_utf7_encode('Junk'),
        ];
        foreach ($boxCollection as $key => $name) {
            $createBox = @\imap_createmailbox(static::$connectionHandler, $name);
            $subscribeToBox = @\imap_subscribe(static::$connectionHandler, $name);
            if (!$createBox || !$subscribeToBox)
            {
                echo '>>> Error creating or subscribe box \''. $name .'\'!' . PHP_EOL;
            }
            echo '... Box \'' . $name . '\' created and subscribed successful!' . PHP_EOL;
        }
    }

    /**
     * Копирует сообщение в нужную папку
     *
     * @param int $destinationPath Путь к папке назначения пользователя
     *
     * @param int $messageNumber Номер сообщения
     *
     * @param string $box Название папки
     *
     * @return bool Возвращает результат операции
     */
    private static function copyToBox($destinationPath, $messageNumber, $box)
    {
        $copyPath = static::$config['host'] . 'INBOX.' . str_replace('.', '', $destinationPath) . '.' . $box;
        return @\imap_mail_copy(static::$connectionHandler, $messageNumber, $copyPath);
    }

    /**
     * Удаляет сообщение
     *
     * @param int $messageNumber Номер сообщения
     *
     * @return bool Возвращает результат операции
     */
    private function removeMessage($messageNumber)
    {
        return @\imap_delete(static::$connectionHandler, $messageNumber);
    }

    /**
     * Применяет изменения
     *
     * @return void Возвращает результат операции
     */
    public function clear()
    {
        echo '... No emails found. Expunge box.' . PHP_EOL;
        if (!@\imap_expunge(static::$connectionHandler))
        {
            echo '>>> Unknown expunge box error. Exiting!' . PHP_EOL;
        }
        echo '... Expunge box done. Well Done! Exiting process.' . PHP_EOL;
    }

    function __destruct()
    {
        static::$connectionHandler = null;
    }
}