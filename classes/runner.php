<?php
/**
 * Created by PhpStorm.
 * User: LifarAV
 * Date: 22.09.14
 * Time: 14:13
 */

namespace Application;

class Runner
{

    static private $connectionHandler;
    static private $config = [];

    function __construct($connectionHandler, array $config)
    {
        if ($connectionHandler === false || $config === false)
        {
            die('Connect Error!');
        }
        static::$connectionHandler = $connectionHandler;
        static::$config = $config;
    }

    private static function getHeaders($i)
    {
        return @\imap_headerinfo(static::$connectionHandler, $i);
    }

    private static function getBoxCollection($username)
    {
        $path = static::$config['host'] . 'INBOX.' . $username;
        $boxes = @\imap_list(static::$connectionHandler, static::$config['host'], '*');
        if (array_search($path, $boxes) && array_search($path . '.Inbox', $boxes) && array_search($path . '.Sent', $boxes) && array_search($path . '.Junk', $boxes))
        {
            return true;
        }
        return false;
    }

    public function execute()
    {
        $messagesCount = @\imap_num_msg(static::$connectionHandler);
        if ($messagesCount > 0)
        {
            for ($i=1; $i<=$messagesCount; ++$i)
            {
                $headers = static::getHeaders($i);

                // Входящее внешнее письмо
                if ($headers->to[0]->host == static::$config['targetDomain'] && $headers->from[0]->host != static::$config['targetDomain'])
                {
                    static::moveToFolder($headers, $i, 'Inbox');
                }
                // Исходящее внешнее письмо
                elseif ($headers->to[0]->host != static::$config['targetDomain'] && $headers->from[0]->host == static::$config['targetDomain'])
                {
                    static::moveToFolder($headers, $i, 'Sent');
                }
                // Внутренняя переписка
                elseif ($headers->to[0]->host == static::$config['targetDomain'] && $headers->from[0]->host == static::$config['targetDomain'])
                {
                    static::copyToFolder([[$headers->to[0]->mailbox,'Inbox'], [$headers->from[0]->mailbox,'Sent']], $i);
                    static::removeMessage($i);
                }
                // Спам
                else
                {
                    static::moveToFolder($headers, $i, 'Junk');
                }
            }
            return static::finish();
        }
    }

    private static function createBoxCollection($username)
    {
        $path = static::$config['host'] . 'INBOX.' . $username;
        $boxCollection = [
            'upper' => $path,
            'inbox' => $path . '.' . imap_utf7_encode('Inbox'),
            'sent' => $path . '.' . imap_utf7_encode('Sent'),
            'junk' => $path . '.' . imap_utf7_encode('Junk'),
        ];
        foreach ($boxCollection as $key => $foldername) {
            if (!@\imap_createmailbox(static::$connectionHandler, $foldername)) return false;
            if (!@\imap_subscribe(static::$connectionHandler, $foldername)) return false;
        }
        return true;
    }

    private static function moveToFolder($headers, $i, $folder)
    {
        $username = str_replace('.', '', $headers->to[0]->mailbox);

        if(!static::getBoxCollection($username))
        {
            if (!static::createBoxCollection($username)) return false;
        }

        if (!@\imap_mail_move(static::$connectionHandler, $i, 'INBOX.' . $username . '.' . $folder)) return false;

        return true;
    }

    /** array $names contains:
     *  [
     *      0 =>
     *      [
     *          0 => 'kupatova',
     *          1 => 'Inbox'
     *      ],
     *      1 =>
     *      [
     *          0 => 'samolova',
     *          1 => 'Sent'
     *      ]
     *  ]
     */
    private static function copyToFolder(array $names, $i)
    {
        foreach ($names as $n => $array)
        {
            $username = str_replace('.', '', $array[0]);

            if(!static::getBoxCollection($username))
            {
                if (!static::createBoxCollection($username)) return false;
            }

            $copyPath = static::$config['host'] . 'INBOX.' . $username . '.' . $array[1];
            if (!@\imap_mail_copy(static::$connectionHandler, $i, $copyPath)) return false;
        }
        return true;
    }

    private static function removeMessage($i)
    {
        return @\imap_delete(static::$connectionHandler, $i);
    }

    private static function finish()
    {
        return @\imap_expunge(static::$connectionHandler);
    }

}