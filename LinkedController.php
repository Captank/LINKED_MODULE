<?php

namespace Budabot\User\Modules;

//Comment the following line if your php version doesn't support namespaces
use \Budabot\Core\AccessManager;

/**
 * Author:
 *  - Captank (RK2)
 *
 * @Instance
 *
 *	@DefineCommand(
 *		command     = 'sync',
 *		accessLevel = 'mod',
 *		description = 'shows the rules',
 *		help        = 'rules.txt'
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
	public $settingManager;
	
	/**
	 * @Setting("botlist")
	 * @Description("bots to sync seperated by ;")
	 * @Visibility("edit")
	 * @Type("text")
	 */
	public $botlist = "";
	
	/**
	 * @Setting("guestbot")
	 * @Description("bot for guests")
	 * @Visibility("edit")
	 * @Type("text")
	 */
	public $guestbot = "";
	
	/**
	 * @Setting("sync_adminlist")
	 * @Description("Synchronize adminlist")
	 * @Visibility("edit")
	 * @Type("options")
	 * @Options("true;false")
	 * @Intoptions("1;0")
	 * @AccessLevel("mod")
	 */
	public $syncAdminlist = "1";
	
	/**
	 * @Setting("sync_conf")
	 * @Description("Synchronize configurations")
	 * @Visibility("edit")
	 * @Type("options")
	 * @Options("true;false")
	 * @Intoptions("1;0")
	 * @AccessLevel("mod")
	 */
	public $syncConf = "1";
	
	/**
	 * @Setting("sync_topic")
	 * @Description("Synchronize topic")
	 * @Visibility("edit")
	 * @Type("options")
	 * @Options("true;false")
	 * @Intoptions("1;0")
	 * @AccessLevel("mod")
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
		if((intval($this->settingManager->get("sync_adminlist")) == 1 && preg_match('/^(add|rem)(mod|admin)/i', $eventObj->message)) ||
		(intval($this->settingManager->get("sync_conf")) == 1 && preg_match('/^(settings save|config (event|mod|cmd) .+ ((dis|en)able|admin (guild|priv|msg) (all|member|guild|rl|mod|admin)))/i', $eventObj->message)) ||
		(intval($this->settingManager->get("sync_topic")) == 1 && preg_match('/^topic .+$/i', $eventObj->message))) {
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
	 * @HandlesCommand("sync")
	 * @Matches("/^sync (([a-z]+) .*)$/i")
	 */
	public function syncCommand($message, $channel, $sender, $sendto, $args) {
		$bots = $this->getBots();
		$access = in_array(strtolower($sender),$bots);
		if(!$access) {
			$access = $this->accessManager->checkAccess($sender, "admin");
		}

		if($access) {
			var_dump($args);
		}
		else {
			$sendto->reply("Error! Access denied.");
		}
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
 }