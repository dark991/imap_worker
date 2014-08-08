<?php
/**
 * Created by PhpStorm.
 * User: LifarAV
 * Date: 17.07.14
 * Time: 14:57
 */

namespace Application;

class Runner
{

    static private $connectionHandler = false;
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
                $header[$i] = @\imap_headerinfo(static::$connectionHandler, $i);

                $hostTo = $header[$i]->to[0]->host;
                $hostFrom = $header[$i]->from[0]->host;

                if ($hostTo == static::$config['targetDomain'] && $hostFrom != static::$config['targetDomain']) // Входящее внешнее письмо
                {
                    static::moveToBox($header[$i], $i, 'Inbox');
                }
                elseif ($hostTo != static::$config['targetDomain'] && $hostFrom == static::$config['targetDomain']) // Исходящее внешнее письмо
                {
                    static::moveToBox($header[$i], $i, 'Sent');
                }
                elseif ($hostTo == static::$config['targetDomain'] && $hostFrom == static::$config['targetDomain']) // Внутренняя переписка
                {
                    static::moveToBox($header[$i], $i, 'Inbox');
                }
                else // Спам
                {
                    static::moveToBox($header[$i], $i, 'Junk');
                }

                $header[$i] = null;
                unset($header[$i]);
            }
        }
        // Если не подключились
        if (static::$connectionHandler === false)
        {
            echo 'Error! Can\'t execute aggregate.'
                . PHP_EOL;
            return false;
        }
        return true;
    }

    private static function moveToBox($messageObject, $messageNumber, $box = 'Inbox')
    {
        // Получаем объект заголовков сообщения.
        // Смотрим название ящика, кому оправляется на домен host.com.
        // Если есть ящик, переносим письмо в него
        // Если ящика нет, создаем его, подписываемся, переносим письмо в него
        $fromArray = [
            'mailbox' => $messageObject->from[0]->mailbox,
            'host' => $messageObject->from[0]->host,
        ];
        $toArray = [
            'mailbox' => $messageObject->to[0]->mailbox,
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
            return false;
        }
        echo '... Good Work! Proceed next email!' . PHP_EOL;
    }

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

    public function clear()
    {
        echo '... No emails found. Expunge box.' . PHP_EOL;
        if (!@\imap_expunge(static::$connectionHandler))
        {
            echo '>>> Unknown expunge box error. Exiting!' . PHP_EOL;
            return false;
        }
        echo '... Expunge box done. Well Done! Exiting process.' . PHP_EOL;
        return true;
    }

    function __destruct()
    {
        static::$connectionHandler = null;
    }
}