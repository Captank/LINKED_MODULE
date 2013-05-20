<?php

namespace Budabot\User\Modules;

//Comment the following line if your php version doesn't support namespaces
use \Budabot\Core\AccessManager;
use \Budabot\Core\CommandManager;

/**
 * Author:
 *  - Captank (RK2)
 *
 * @Instance
 *
 *	@DefineCommand(
 *		command     = 'sync',
 *		accessLevel = 'admin',
 *		description = 'sync cmd between bots',
 *		help        = 'sync.txt',
 *		channels	= 'msg'
 *	)
  *	@DefineCommand(
 *		command     = 'guest',
 *		accessLevel = 'mod',
 *		description = 'handles guest list',
 *		help        = 'sync.txt'
 *	)
 */
class LinkedController {
	public $moduleName;
	
	/** @Inject */
	public $chatBot;
	
	/** @Inject */
	public $setting;
	
	/** @Inject */
	public $accessManager;
	
	/** @Inject */
	public $commandManager;
	
	/** @Inject */
	public $settingManager;
	
	/**
	 * @Setting("botlist")
	 * @Description("bots to sync seperated by ;")
	 * @Visibility("edit")
	 * @Type("text")
	 * @AccessLevel("admin")
	 */
	public $botlist = "";
	
	/**
	 * @Setting("guestbot")
	 * @Description("bot for guests")
	 * @Visibility("edit")
	 * @Type("text")
	 * @AccessLevel("admin")
	 */
	public $guestbot = "";
	
	/**
	 * @Setting("sync_adminlist")
	 * @Description("Synchronize adminlist")
	 * @Visibility("edit")
	 * @Type("options")
	 * @Options("true;false")
	 * @Intoptions("1;0")
	 * @AccessLevel("admin")
	 */
	public $syncAdminlist = "1";
	
	/**
	 * @Setting("sync_conf")
	 * @Description("Synchronize configurations")
	 * @Visibility("edit")
	 * @Type("options")
	 * @Options("true;false")
	 * @Intoptions("1;0")
	 * @AccessLevel("admin")
	 */
	public $syncConf = "1";
	
	/**
	 * @Setting("sync_topic")
	 * @Description("Synchronize topic")
	 * @Visibility("edit")
	 * @Type("options")
	 * @Options("true;false")
	 * @Intoptions("1;0")
	 * @AccessLevel("admin")
	 */
	public $syncTopic = "1";
	
	/**
	 * @Event("msg")
	 * @Description("command hook for tell messages")
	 */
	public function tellHandler($eventObj) {
		$this->handle($eventObj);
	}
	
	/**
	 * @Event("priv")
	 * @Description("command hook for priv messages")
	 */
	public function privHandler($eventObj) {
		if($eventObj->message[0] == $this->setting->symbol) {
			$this->handle($eventObj);
		}
	}
	
	/**
	 * @Event("guild")
	 * @Description("command hook for guild messages")
	 */
	public function guildHandler($eventObj) {
		if($eventObj->message[0] == $this->setting->symbol) {
			$this->handle($eventObj);
		}
	}
	
	/**
	 * This function determines if a command will be relayed to synchronize bots.
	 *
	 * @param object - event object
	 */
	public function handle($eventObj) {
		$eventObj->message = preg_replace("~^{$this->setting->symbol}~",'',$eventObj->message);
		$msg = false;
		if($this->shallSynchronize($eventObj->message) && $this->checkAccess($eventObj->sender,$eventObj->type,$eventObj->message)) {
			$bots = $this->getBots();
			$msg = 'sync '.$eventObj->message;
		}
		if($msg) {
			foreach($bots as $sendto) {
				$this->chatBot->sendTell($msg, $sendto);
			}
		}
	}
	
	/**
	 * Synchronization handler.
	 *
	 * @HandlesCommand("sync")
	 * @Matches("/^sync (([a-z]+) .*)$/i")
	 */
	public function syncCommand($message, $channel, $sender, $sendto, $args) {
		$bots = $this->getBots();
		$access = in_array(strtolower($sender),$bots);
		if(!$access) {
			$access = $this->accessManager->checkAccess($sender, "admin");
		}

		if($access && $this->shallSynchronize($args[1])) {
			$this->commandManager->process($channel, $args[1], $sender, new DummyBuffer());
		}
	}
	
	/**
	 * @HandlesCommand("guest")
	 * @Matches("/^guest/i")
	 */
	public function guestCommand($message, $channel, $sender, $sendto, $args) {
	
	}
	
	/**
	 * Get all bots that need to be kept in sync.
	 *
	 * @return array - all bots but self.
	 */
	private function getBots() {
		$bots = $this->settingManager->get("botlist");
		$bots = explode(";", $bots);
		$me = strtolower($this->chatBot->vars['name']);
		foreach($bots as $i => &$b_) {
			$b_ = strtolower($b_);
		}
		foreach($bots as $i => $b) {
			if($me == $b) {
				unset($bots[$i]);
				break;
			}
		}
		return $bots;
	}
	
	/**
	 * Checks if sender has access to run the command.
	 *
	 * @param string $sender - sender of command
	 * @param string $channel - channel the command was send to
	 * @param string $message - whole message
	 * @param boolean - true if sender has permission
	 */
	public function checkAccess($sender, $channel, $message) {
		list($cmd, $params) = explode(' ', $message, 2);
		$cmd = strtolower($cmd);
		$commandHandler = $this->commandManager->getActiveCommandHandler($cmd, $channel, $message);
		if ($commandHandler === null) {
			return false;
		}
		return $this->accessManager->checkAccess($sender, $commandHandler->admin) === true;
	}
	
	/**
	 * Checks if command be synchronized.
	 *
	 * @param string $command - the command
	 * @return boolean - true if it shall be synchronized.
	 */
	public function shallSynchronize($command) {
		return 	(intval($this->settingManager->get("sync_adminlist")) == 1 && preg_match('/^(add|rem)(mod|admin)/i', $command)) ||
				(intval($this->settingManager->get("sync_conf")) == 1 && preg_match('/^(settings save|config (event|mod|cmd) .+ ((dis|en)able|admin (guild|priv|msg) (all|member|guild|rl|mod|admin)))/i', $command)) ||
				(intval($this->settingManager->get("sync_topic")) == 1 && preg_match('/^topic .+$/i', $command));
	}
}

use \Budabot\Core\CommandReply;

class DummyBuffer implements CommandReply {
	public function reply($msg) {}
}