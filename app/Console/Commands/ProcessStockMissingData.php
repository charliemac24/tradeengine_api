<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

/**
 * Artisan command wrapper for running a ProcessStockMissingData script/class.
 *
 * Usage examples:
 *  php artisan stock:process-missing --file=path/to/script.php
 *  php artisan stock:process-missing --class=\App\Scripts\ProcessStockMissingData
 */
class ProcessStockMissingData extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'stock:process-missing {--file= : Optional PHP file to include} {--class=ProcessStockMissingData : Class name to instantiate (FQCN or short)}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Run ProcessStockMissingData script/class to fill missing stock data.';

	/**
	 * Execute the console command.
	 *
	 * Attempts to include an optional file, then resolves the requested class
	 * from the container (or with new) and calls one of: handle(), run(), process(), or invoke.
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$file = $this->option('file');
		$class = $this->option('class') ?: 'ProcessStockMissingData';

		if ($file) {
			if (! file_exists($file)) {
				$this->error("File not found: $file");
				return 1;
			}
			require_once $file;
			$this->info("Included file: $file");
		}

		// Try to resolve via container first for DI, fallback to class_exists/new
		try {
			if (class_exists($class)) {
				// Prefer container to support constructor injection
				try {
					$instance = app()->make($class);
				} catch (\Throwable $e) {
					// Fallback to direct instantiation if container resolution fails
					$instance = new $class();
				}
			} else {
				$this->error("Class '$class' not found.");
				return 1;
			}

			$this->info("Using class: " . get_class($instance));

			if (method_exists($instance, 'handle')) {
				$this->info('Calling handle()');
				$result = $instance->handle();
			} elseif (method_exists($instance, 'run')) {
				$this->info('Calling run()');
				$result = $instance->run();
			} elseif (method_exists($instance, 'process')) {
				$this->info('Calling process()');
				$result = $instance->process();
			} elseif (is_callable($instance)) {
				$this->info('Invoking object as callable');
				$result = $instance();
			} else {
				$this->error('No callable entrypoint (handle/run/process/__invoke) found on class.');
				return 1;
			}

			$this->info('Execution finished.');
			return ($result === 1) ? 1 : 0;
		} catch (Exception $e) {
			$this->error('Error: ' . $e->getMessage());
			return 1;
		}
	}
}

