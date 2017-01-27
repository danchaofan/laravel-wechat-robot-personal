<?php

namespace App\Console\Commands;

use Cache;
use Carbon\Carbon;
use Hanson\Vbot\Message\Entity\Image as VbotImage;
use Hanson\Vbot\Message\Entity\Text as VbotText;
use Hanson\Vbot\Message\Entity\Emoticon as VbotEmoticon;
use Hanson\Vbot\Message\Entity\Location as VbotLocation;
use Hanson\Vbot\Message\Entity\Video as VbotVideo;
use Hanson\Vbot\Message\Entity\Voice as VbotVoice;
use Hanson\Vbot\Message\Entity\Recall as VbotRecall;
use Hanson\Vbot\Message\Entity\RedPacket as VbotRedPacket;
use Hanson\Vbot\Message\Entity\Transfer as VbotTransfer;
use Hanson\Vbot\Message\Entity\Recommend as VbotRecommend;
use Hanson\Vbot\Message\Entity\Share as VbotShare;
use Hanson\Vbot\Message\Entity\Touch as VbotTouch;
use Hanson\Vbot\Message\Entity\RequestFriend as VbotRequestFriend;

class Bainian2 extends VbotBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bainian2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拜年';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->tasks[] = ['name' => 'touchSendAndReply', 'description' => '点击对话自动发送预设的祝福、联系人回复消息自动发送预设的回应'];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->callHandle();
    }

    /**
     * 点击对话自动发送预设的祝福、联系人消息自动回复预设的回应
     */
    public function touchSendAndReply()
    {
        // 预置顺序我的祝福（点触对话一次，发送一次）
        $wishes = [
//            1 => "祝：春节快乐，鸡年大吉！",
            1 => "时间的朋友\n\n春节快乐，鸡年大吉\n身体健康，万事顺利！！",
        ];

        // 预置顺序我的回复（收到消息一次，发送一次）
        $replies = [
            1 => "福从天降，感谢有你…",
            2 => "2017 财源滚滚！！",
//            3 => "[福][福][福]\n[鸡][鸡][鸡][鸡][鸡][鸡]",
        ];

        $this->vbot->server->setMessageHandler(function ($message) use ($wishes, $replies) {

            // 若遇异常只需报出异常信息而不中断进程
            try {

                // 手指点入聊天：自动发送祝福，只回复联系人，不响应群点击
                if ($message instanceof VbotTouch) {

                    if (!starts_with($message->msg['ToUserName'], '@@') && starts_with($message->msg['ToUserName'], '@')) {
                        $touchTimes = Cache::get("2UserName_{$message->msg['ToUserName']}_touch", 0);

                        Cache::forever("2UserName_{$message->msg['ToUserName']}_touch", ++$touchTimes);

                        if (count($wishes) > $touchTimes - 1) {
                            $this->info("\nWish To [{$message->msg['ToUserName']}]# {$touchTimes}");

                            VbotText::Send($message->msg['ToUserName'], $wishes[$touchTimes]);
                        }
                    }
                }

                // 联系人发来文字信息
                if ($message instanceof VbotText) {
                    if (!starts_with($message->msg['FromUserName'], '@@') && starts_with($message->msg['FromUserName'], '@')) {

                        $replyTimes = Cache::get("2UserName_{$message->msg['FromUserName']}_reply", 0);

                        Cache::forever("2UserName_{$message->msg['FromUserName']}_reply", ++$replyTimes);

                        $this->warn("\nContact [{$message->msg['FromUserName']}]\nMessage.{$replyTimes}# {$message->content}");

                        if (count($replies) >= $replyTimes) {
                            $this->info("\n$ {$replies[$replyTimes]}");

                            sleep(1);
                            VbotText::send($message->msg['FromUserName'], $replies[$replyTimes]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("\n\n[Error #.{$e->getCode()}] Line.{$e->getLine()} {$e->getMessage()}\n");
            }

            return false;
        });
    }
}
