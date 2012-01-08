<?php
App::uses('Shell', 'Console');

class ClassPathShell extends Shell {

	public $excluded = array(
		'Config',
		'Console',
		'Test',
		'TestSuite',
		'Model/Datasource/Database/Postgres.php',
		'Model/Datasource/Database/SqlServer.php',
		'Cache/Engine/XcacheEngine',
		'Cache/Engine/WincacheEngine',
		'tmp'
	);

	public function main() {
		$dir = ROOT;
		$files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)), '/\.php$/');
		foreach ($files as $item) {
			if ($item->isDir()) {
				continue;
			}
			foreach ($this->excluded as $package) {
				if (strpos($item->getRealPath(), APP . $package) === 0 || strpos($item->getRealPath(), CAKE . $package) === 0) {
					continue(2);
				}
				foreach (CakePlugin::loaded() as $plugin) {
					if (strpos($item->getRealPath(), CakePlugin::path($plugin) . $package) === 0) {
						continue(3);
					}
				}
			}
			if (!in_array($item->getRealPath(), get_included_files()) && $this->hasClass($item->getRealPath())) {
				include_once $item->getRealPath();
			}
		}
		$processdList = $this->classFiles(get_included_files());
		$this->writeIncludes($processdList);
	}

	protected function classFiles($fileList) {
		$finalList = array();
		foreach ($fileList as $file) {
			if ($this->hasClass($file)) {
				$finalList[] = $file;
			}
		}
		return $finalList;
	}

	protected function hasClass($file) {
		$php_code = file_get_contents($file);
		$classes = array();
		$tokens = token_get_all($php_code);
		$count = count($tokens);
		for ($i = 2; $i < $count; $i++) {
			if ($tokens[$i - 2][0] == T_CLASS
				&& $tokens[$i - 1][0] == T_WHITESPACE
				&& $tokens[$i][0] == T_STRING) {

				return true;
			}
		}
		return false;
	}

	protected function writeIncludes($list) {
		$result = '';
		foreach ($list as $file) {
			foreach(array_merge(App::path('Console'), App::core('Console')) as $path) {
				if (strpos($file, $path) === 0) {
					continue(2);
				}
			}
			if ($file === __FILE__) {
				continue;
			}
			$file = str_replace(CAKE, '../../lib/Cake/', $file);
			$file = str_replace(APP, '../', $file);
			$result .= "include_once('$file'); \n";
		}
		file_put_contents(APP . 'Config' . DS . 'includes.php', "<?php\n" . $result);
	}
}
