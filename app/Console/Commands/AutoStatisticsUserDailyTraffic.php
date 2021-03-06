<?php

namespace App\Console\Commands;

use App\Models\SsNode;
use App\Models\User;
use App\Models\UserTrafficDaily;
use App\Models\UserTrafficLog;
use Illuminate\Console\Command;
use Log;

class AutoStatisticsUserDailyTraffic extends Command {
	protected $signature = 'autoStatisticsUserDailyTraffic';
	protected $description = '自动统计用户每日流量';

	public function handle(): void {
		$jobStartTime = microtime(true);

		$userList = User::query()->where('status', '>=', 0)->whereEnable(1)->get();
		foreach($userList as $user){
			// 统计一次所有节点的总和
			$this->statisticsByNode($user->id);

			// 统计每个节点产生的流量
			$nodeList = SsNode::query()->whereStatus(1)->orderBy('id')->get();
			foreach($nodeList as $node){
				$this->statisticsByNode($user->id, $node->id);
			}
		}

		$jobEndTime = microtime(true);
		$jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

		Log::info('---【'.$this->description.'】完成---，耗时'.$jobUsedTime.'秒');
	}

	private function statisticsByNode($user_id, $node_id = 0): void {
		$start_time = strtotime(date('Y-m-d 00:00:00', strtotime("-1 day")));
		$end_time = strtotime(date('Y-m-d 23:59:59', strtotime("-1 day")));

		$query = UserTrafficLog::query()->whereUserId($user_id)->whereBetween('log_time', [$start_time, $end_time]);

		if($node_id){
			$query->whereNodeId($node_id);
		}

		$u = $query->sum('u');
		$d = $query->sum('d');
		$total = $u + $d;
		$traffic = flowAutoShow($total);

		if($total){ // 有数据才记录
			$obj = new UserTrafficDaily();
			$obj->user_id = $user_id;
			$obj->node_id = $node_id;
			$obj->u = $u;
			$obj->d = $d;
			$obj->total = $total;
			$obj->traffic = $traffic;
			$obj->save();
		}
	}
}
