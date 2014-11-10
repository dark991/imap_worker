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
//        // Производим чистку писем
//        $messages = @\imap_num_msg(static::$connectionHandler);
//        if ($messages > 0) {
//            foreach ($messages as $message) {
//                static::removeMessage($message);
//            }
//        }

//        // Производим чистку папок
//        $folders = @\imap_list(static::$connectionHandler, static::$config['host'], 'INBOX.*');
//        if ($folders) {
//            foreach ($folders as $key => $name) {
//                @\imap_deletemailbox(static::$connectionHandler,$name);
//            }
//        }

        // Распределяем письма по папкам
        $messagesCount = @\imap_num_msg(static::$connectionHandler);
        if ($messagesCount > 0)
        {
            for ($i=1; $i<=$messagesCount; ++$i)
            {
                $headers = static::getHeaders($i);

                if (!isset($headers->to[0]->host) || !isset($headers->from[0]->host))
                {
                    echo $i . '. To: \'' . $headers->to[0]->host . '\' From: \'' . $headers->from[0]->host . '\'';
                    continue;
                }

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
            static::finish();
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
            @\imap_createmailbox(static::$connectionHandler, $foldername);
            @\imap_subscribe(static::$connectionHandler, $foldername);
        }
    }

    private static function moveToFolder($headers, $i, $folder = 'Junk')
    {
        if ($folder=='Inbox') {
            $username = str_replace('.', '', $headers->to[0]->mailbox);
            $path = 'INBOX.' . $username . '.' . $folder;
        }
        elseif ($folder=='Sent') {
            $username = str_replace('.', '', $headers->from[0]->mailbox);
            $path = 'INBOX.' . $username . '.' . $folder;
        }
        elseif ($folder=='Junk') {
            $username = str_replace('.', '', $headers->to[0]->mailbox);
            //$path = 'INBOX.' . $username . '.Junk';
            $path = 'Junk';
        }

        if (isset($username)){
            $username = ucfirst($username);
            if(!static::getBoxCollection($username))
            {
                static::createBoxCollection($username);
            }
        }

        @\imap_mail_move(static::$connectionHandler, $i, $path);
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
            $username = ucfirst($username);

            if(!static::getBoxCollection($username))
            {
                static::createBoxCollection($username);
            }

            $copyPath = static::$config['host'] . 'INBOX.' . $username . '.' . $array[1];
            @\imap_mail_copy(static::$connectionHandler, $i, $copyPath);
        }
    }

    private static function removeMessage($i)
    {
        @\imap_delete(static::$connectionHandler, $i);
    }

    private static function finish()
    {
        @\imap_expunge(static::$connectionHandler);
    }

}