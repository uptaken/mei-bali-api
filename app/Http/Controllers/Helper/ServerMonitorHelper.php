<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\JsonResponse;

class ServerMonitorHelper
{
	public function getStatus(){
		return [
			'cpu_usage' => $this->getServerCpuUsage(),
			'memory' => $this->getServerMemoryUsage(),
			'swap' => $this->getServerSwapUsage(),
		];
	}

	/**
	 * Get Server CPU Usage (1-minute Load Average)
	 */
	private function getServerCpuUsage(): float{
		// sys_getloadavg() returns an array with 1, 5, and 15 min load averages
		$load = sys_getloadavg();
		return isset($load[0]) ? round($load[0], 2) : 0.0;
	}

	/**
	 * Get Server Memory Usage Percentage (Linux environments)
	 */
	private function getServerMemoryUsage(): array{
		$free = shell_exec('free -m');

		if (!$free) {
			return ['error' => 'Unable to retrieve memory data'];
		}

		// Clean up the output string
		$free = trim((string)$free);
		$lines = explode("\n", $free);

		// Isolate the row containing actual memory stats
		$memInfo = explode(" ", $lines[1]);
		$memInfo = array_filter($memInfo); // Remove empty array slots
		$memInfo = array_values($memInfo); // Reset array indexing

		// $memInfo[1] = Total, $memInfo[2] = Used
		$totalMem = (int)$memInfo[1];
		$usedMem = (int)$memInfo[2];

		$percentage = $totalMem > 0 ? ($usedMem / $totalMem) * 100 : 0;

		return [
			'total_mb'   => $totalMem,
			'used_mb'    => $usedMem,
			'percentage' => round($percentage, 2) . '%'
		];
	}

	/**
	 * Get Server Memory Usage Percentage (Linux environments)
	 */
	private function getServerSwapUsage(): array{
		$free = shell_exec('free -m');

		if (!$free) {
			return ['error' => 'Unable to retrieve memory data'];
		}

		// Clean up the output string
		$free = trim((string)$free);
		$lines = explode("\n", $free);

		// Isolate the row containing actual memory stats
		$memInfo = explode(" ", $lines[2]);
		$memInfo = array_filter($memInfo); // Remove empty array slots
		$memInfo = array_values($memInfo); // Reset array indexing

		// $memInfo[1] = Total, $memInfo[2] = Used
		$totalMem = (int)$memInfo[1];
		$usedMem = (int)$memInfo[2];

		$percentage = $totalMem > 0 ? ($usedMem / $totalMem) * 100 : 0;

		return [
			'total_mb'   => $totalMem,
			'used_mb'    => $usedMem,
			'percentage' => round($percentage, 2) . '%'
		];
	}
}