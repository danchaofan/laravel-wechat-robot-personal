<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Cache;
use Carbon\Carbon;
use Hanson\Vbot\Foundation\Vbot;
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

class VbotBaseCommand extends Command
{
    /**
     * Hanson\Vbot\Foundation\Vbot;
     */
    public $vbot;

    /**
     * 会话期间的资源文件存储位置
     *
     * @var string
     */
    public $vbotStoragePath;

    /**
     * 是否开启 vbot 的 debug 模式
     *
     * @var boolean
     */
    public $vbotDebugMode;

    /**
     * 常用用户列表
     *
     * @var array
     */
    public $wechatUsers = [];

    /**
     * 使用人的微信号（用于设立存储文件夹）
     *
     * @var string
     */
    public $wechatUserId;

    /**
     * 自己的微信昵称（也是群友称呼自己的默认昵称）
     *
     * @var string
     */
    public $wechatNickname;

    /**
     * 把特定的消息发给自己预置好名字的群组（可用于简易通知，比如红包通知或好友验证信息）
     *
     * @var string
     */
    public $wechatGroupname;

    /**
     * 可执行的任务
     *
     * @var array
     */
    public $tasks = [
        ['name' => 'contactsList', 'description' => '联系人列表'],
    ];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function callHandle()
    {
        // 确认配置
        $this->vbotDebugMode = env('VBOT_DEBUG_MODE', true);
        $this->wechatNickname = env('VBOT_DEFAULT_WECHAT_NICKNAME');

        // 默认参数完整性确认
        if (!$this->wechatUserId = env('VBOT_DEFAULT_WECHAT_ID')) {
            throw new \Exception("请完善项目 .env 文件中的 VBOT_DEFAULT_WECHAT_ID 默认参数", 1);
        }
        if (!$this->wechatNickname = env('VBOT_DEFAULT_WECHAT_NICKNAME')) {
            throw new \Exception("请完善项目 .env 文件中的 VBOT_DEFAULT_WECHAT_NICKNAME 默认参数", 1);
        }
        if (!$this->wechatGroupname = env('VBOT_DEFAULT_WECHAT_GROUPNAME')) {
            throw new \Exception("请完善项目 .env 文件中的 VBOT_DEFAULT_WECHAT_GROUPNAME 默认参数", 1);
        }

        $this->wechatUsers[] = [
            'wechat_userid' => $this->wechatUserId,
            'wechat_nickname' => $this->wechatNickname,
            'wechat_groupname' => $this->wechatGroupname,
        ];

        // 如果配置了其它微信号
        for ($i = 2; $i <= 10; $i++) {
            if (!$wechatUserId = env("VBOT_DEFAULT_WECHAT_ID_{$i}")) {
                continue;
            }
            if (!$wechatNickname = env("VBOT_DEFAULT_WECHAT_NICKNAME_{$i}")) {
                $this->warn("未能识别或未设置 VBOT_DEFAULT_WECHAT_NICKNAME_{$i}");
                continue;
            }
            if (!$wechatGroupName = env("VBOT_DEFAULT_WECHAT_GROUPNAME_{$i}")) {
                $this->warn("未能识别或未设置 VBOT_DEFAULT_WECHAT_GROUPNAME_{$i}");
                continue;
            }

            $this->wechatUsers[] = [
                'wechat_userid' => $wechatUserId,
                'wechat_nickname' => $wechatNickname,
                'wechat_groupname' => $wechatGroupName,
            ];
        }

        // 配置了多微信号可选时，提供选项
        $this->wechatUsers = collect($this->wechatUsers);
        if (1 < $this->wechatUsers->count()) {
            $this->warn("\nWechat User list:");

            $this->wechatUsers->each(function ($wechatUser, $key) {
                $this->info("{$key}: {$wechatUser['wechat_nickname']}");
            });

            // 多用户时默认选择 0
            $wechatUserKey = $this->ask('Select a wechat user to use:', 0);

            $wechatUser = $this->wechatUsers->first(function ($tmp, $key) use ($wechatUserKey) {
                if ($key == $wechatUserKey) {
                    return true;
                } else {
                    return false;
                }
            });

            if (!$wechatUser) {
                throw new \Exception("No User is selected", 1);
            }

            $this->wechatUserId = $wechatUser['wechat_userid'];
            $this->wechatNickname = $wechatUser['wechat_nickname'];
            $this->wechatGroupname = $wechatUser['wechat_groupname'];
        }

        // 根据选择的用户设置存储路径
        $this->vbotStoragePath = storage_path('vbot/' . $this->wechatUserId);


        // 多个任务可选时，提供选项
        $this->tasks = collect($this->tasks);
        if (1 < $this->tasks->count()) {
            $this->warn("\nTasks list:");

            $this->tasks->each(function ($task, $key) {
                $this->info("{$key}: {$task['description']}");
            });

            // 有自定义任务时默认选择 1
            $taskKey = $this->ask('Select a task to run:', 1);

            $task = $this->tasks->first(function ($task, $key) use ($taskKey) {
                if ($key == $taskKey) {
                    return true;
                } else {
                    return false;
                }
            });

            if (!$task) {
                throw new \Exception("No Task is selected", 1);
            }

            $taskName = $task['name'];

        } else {
            $taskName = $this->tasks->first()['name'];
        }

        // VBOT 实例化
        $this->vbot = new Vbot([
            'tmp' => $this->vbotStoragePath,
            'debug' => $this->vbotDebugMode,
        ]);

        // 执行任务
        $this->$taskName();

        // 其它设备登录，网页版微信退出
        $this->vbot->server->setExitHandler(function () {
            $this->warn('其他设备登录');
        });

        // 异常退出
        $this->vbot->server->setExceptionHandler(function () {
            $this->warn('异常退出');
        });

        // VBOT 启动工作
        try {
            $this->vbot->server->run();
        } catch (\Exception $e) {
            if (!$this->confirm("\nRetry", true)) {
                return null;
            }

            $this->vbot->server->run();
        }
    }

    /**
     * 联系人列表
     */
    public function contactsList()
    {
        $this->vbot->server->setCustomerHandler(function () {

            // 若遇异常只需报出异常信息而不中断进程
            try {
                contact()->each(function ($contact) {
                    $this->warn("\n{$contact['UserName']}");
                    $this->info("[微信号] {$contact['Alias']}\n[ 昵称 ] {$contact['NickName']}\n[ 备注 ] {$contact['RemarkName']}");
                });

                exit();
            } catch (\Exception $e) {
                $this->error("\n\n[Error #.{$e->getCode()}] Line.{$e->getLine()} {$e->getMessage()}\n");
            }

            return false;
        });
    }

    /**
     * 使用图灵机器人回复
     *
     * @param $str
     * @param null $userId
     * @return string
     */
    public function reply($str, $userId = null)
    {
        $data = [];
        $data['key'] = config('turing.key');
        $data['info'] = $str;
        $userId ? ($data['userid'] = md5($userId)) : null;

        return http()->post('http://www.tuling123.com/openapi/api', $data, true)['text'];
    }
}
