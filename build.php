<?php
/**
 * Собирает ocn_language_russian.ocmod.zip.
 * Каркас берётся из этого репозитория, фразы — из ветки OpenCart через git archive
 * (рабочая ветка форка не переключается).
 *
 * php ocn/language/build.php [версия] [--version=версия] [--copy] [--tag=тег]
 * Без версии читается константа VERSION ветки OPENCART_BRANCH.
 * Без --copy и --tag меняется только zip в dist/. --tag подразумевает --copy.
 * Справка: php ocn/language/build.php --help
 */

$scriptDir = realpath(__DIR__);

if ($scriptDir === false) {
	fwrite(STDERR, "Не удалось определить каталог скрипта.\n");
	exit(1);
}

$requested = parseBuildArgs(scriptArgs());

$examplePath = $scriptDir . DIRECTORY_SEPARATOR . '.env.example';
$envPath = $scriptDir . DIRECTORY_SEPARATOR . '.env';

if (!is_file($examplePath)) {
	fail('Не найден ' . $examplePath);
}

$defaults = [
	'OPENCART_REPO'   => '',
	'OPENCART_BRANCH' => 'lang-v4',
	'PACKAGE_DIR'     => '',
];
$example = array_merge($defaults, parseEnvFile($examplePath));

if (!is_file($envPath)) {
	fwrite(STDERR, "Нет ocn/language/.env. Скопируйте .env.example в .env, чтобы задать пути.\n");
	fwrite(STDERR, "Сейчас используются значения по умолчанию из .env.example.\n");
	$config = $example;
} else {
	$config = array_merge($example, parseEnvFile($envPath));
}

$repo = trim((string)$config['OPENCART_REPO']);
$branch = trim((string)$config['OPENCART_BRANCH']);
$packageDir = trim((string)$config['PACKAGE_DIR']);

if ($repo === '') {
	$repo = dirname($scriptDir, 2);
}

if ($branch === '') {
	$branch = 'lang-v4';
}

if ($packageDir === '') {
	$packageDir = $scriptDir;
}

if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9._/\-]*$#', $branch) || str_contains($branch, '..')) {
	fail('Некорректное имя ветки OPENCART_BRANCH: ' . $branch);
}

$repo = toAbsolute($repo);
$packageDir = toAbsolute($packageDir);

if (!is_dir($repo . DIRECTORY_SEPARATOR . '.git') && !is_file($repo . DIRECTORY_SEPARATOR . '.git')) {
	fail('OPENCART_REPO не является git-репозиторием: ' . $repo);
}

$packageRoot = packageRoot($packageDir);
$install = readInstall($packageRoot . DIRECTORY_SEPARATOR . 'install.json');
$version = $requested['version'] ?? opencartVersion($repo, $branch);
$tag = $requested['tag'];
$copy = $requested['copy'] || $tag !== null;
$packageGit = null;

if ($copy) {
	$packageGit = packageGitRoot($packageDir);

	if ($tag !== null) {
		assertPackageClean($packageGit);
		assertTagFree($packageGit, $tag);
	}
}

$buildRoot = $scriptDir . DIRECTORY_SEPARATOR . 'build';
$extractDir = $buildRoot . DIRECTORY_SEPARATOR . 'src';
$stagingDir = $buildRoot . DIRECTORY_SEPARATOR . 'staging';
$distDir = $scriptDir . DIRECTORY_SEPARATOR . 'dist';
$zipPath = $distDir . DIRECTORY_SEPARATOR . 'ocn_language_russian.ocmod.zip';

assertInside($scriptDir, $buildRoot);
removeTree($buildRoot);

if (!mkdir($extractDir, 0777, true) && !is_dir($extractDir)) {
	fail('Не удалось создать ' . $extractDir);
}

if (!mkdir($stagingDir, 0777, true) && !is_dir($stagingDir)) {
	fail('Не удалось создать ' . $stagingDir);
}

$skeletonCount = copySkeleton($packageRoot, $stagingDir);
$archivePaths = [
	'upload/admin/language/ru-ru'                     => 'admin/language/ru-ru/',
	'upload/catalog/language/ru-ru'                   => 'catalog/language/ru-ru/',
	'upload/extension/opencart/admin/language/ru-ru'  => 'admin/language/ru-ru/extension/opencart/',
	'upload/extension/opencart/catalog/language/ru-ru' => 'catalog/language/ru-ru/extension/opencart/',
];

$tarPath = $buildRoot . DIRECTORY_SEPARATOR . 'source.tar';
$archiveArgs = array_merge(
	['archive', '--format=tar', '--output=' . $tarPath, $branch, '--'],
	array_keys($archivePaths)
);
runGit($repo, $archiveArgs);

if (!is_file($tarPath)) {
	fail('git archive не создал ' . $tarPath);
}

extractTar($tarPath, $extractDir);
unset($tarPath);
@unlink($buildRoot . DIRECTORY_SEPARATOR . 'source.tar');

$phraseStats = copyPhrases($extractDir, $stagingDir, $archivePaths);

$install['version'] = $version;
$installJson = encodeInstall([
	'name'        => $install['name'],
	'description' => $install['description'],
	'code'        => $install['code'],
	'version'     => $version,
	'author'      => $install['author'],
	'link'        => $install['link'],
]);
writeFile($stagingDir . DIRECTORY_SEPARATOR . 'install.json', $installJson);

if (!is_dir($distDir) && !mkdir($distDir, 0777, true) && !is_dir($distDir)) {
	fail('Не удалось создать ' . $distDir);
}

if (is_file($zipPath)) {
	unlink($zipPath);
}

writeZip($stagingDir, $zipPath);
verifyZip($zipPath, $repo, $branch, $packageRoot, $version);

fwrite(STDOUT, 'Версия OpenCart: ' . $version . PHP_EOL);
fwrite(STDOUT, 'Файлов каркаса: ' . $skeletonCount . PHP_EOL);
fwrite(STDOUT, 'Файлов фраз: ' . $phraseStats['copied'] . PHP_EOL);
fwrite(STDOUT, 'Пропущено: ' . $phraseStats['skipped'] . PHP_EOL);
fwrite(STDOUT, 'Архив: ' . $zipPath . PHP_EOL);

if ($packageGit !== null) {
	publishPackage($packageGit, $repo, $packageRoot, $stagingDir, $version, $tag, $zipPath);
}

function fail(string $message): void {
	fwrite(STDERR, $message . PHP_EOL);
	exit(1);
}

function parseEnvFile(string $path): array {
	$lines = file($path, FILE_IGNORE_NEW_LINES);

	if ($lines === false) {
		fail('Не удалось прочитать ' . $path);
	}

	$vars = [];

	foreach ($lines as $line) {
		$line = trim($line);

		if (str_starts_with($line, "\xEF\xBB\xBF")) {
			$line = substr($line, 3);
		}

		if ($line === '' || str_starts_with($line, '#')) {
			continue;
		}

		if (str_starts_with($line, 'export ')) {
			$line = trim(substr($line, 7));
		}

		$eq = strpos($line, '=');

		if ($eq === false) {
			continue;
		}

		$key = trim(substr($line, 0, $eq));
		$value = trim(substr($line, $eq + 1));

		if (
			(str_starts_with($value, '"') && str_ends_with($value, '"'))
			|| (str_starts_with($value, "'") && str_ends_with($value, "'"))
		) {
			$value = substr($value, 1, -1);
		}

		$vars[$key] = $value;
	}

	return $vars;
}

function toAbsolute(string $path): string {
	$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
	$isAbsolute = (bool)preg_match('#^[A-Za-z]:[\\\\/]#', $path) || str_starts_with($path, '\\\\');

	if (!$isAbsolute) {
		$cwd = getcwd();

		if ($cwd === false) {
			fail('Не удалось определить текущий каталог');
		}

		$path = $cwd . DIRECTORY_SEPARATOR . $path;
	}

	$real = realpath($path);

	if ($real === false) {
		fail('Каталог не найден: ' . $path);
	}

	return $real;
}

function packageRoot(string $packageDir): string {
	$candidates = [
		$packageDir . DIRECTORY_SEPARATOR . 'ocn_language_russian.ocmod',
		$packageDir,
	];

	foreach ($candidates as $dir) {
		if (is_file($dir . DIRECTORY_SEPARATOR . 'install.json')) {
			return $dir;
		}
	}

	fail('В пакете нет install.json: ' . $packageDir);
}

function readInstall(string $path): array {
	$raw = file_get_contents($path);

	if ($raw === false) {
		fail('Не удалось прочитать ' . $path);
	}

	$data = json_decode($raw, true);

	if (!is_array($data)) {
		fail('Не удалось разобрать ' . $path);
	}

	foreach (['name', 'code', 'author', 'link', 'description'] as $key) {
		if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
			fail('В install.json пакета нет строки ' . $key);
		}
	}

	return $data;
}

function opencartVersion(string $repo, string $branch): string {
	$index = runGit($repo, ['show', $branch . ':upload/index.php']);

	if (!preg_match('/define\(\s*[\'"]VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $index, $match)) {
		fail('В ' . $branch . ':upload/index.php не найдена константа VERSION');
	}

	return $match[1];
}

function scriptArgs(): array {
	if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
		return $_SERVER['argv'];
	}

	return [];
}

function buildUsage(): string {
	return implode(PHP_EOL, [
		'Сборка русификатора. Запуск из корня форка или из каталога скрипта:',
		'  php ocn/language/build.php',
		'  php build.php 4.1.0.4',
		'  php build.php --version=4.1.0.4',
		'  php build.php 4.1.0.4 --copy',
		'  php ocn/language/build.php 4.1.0.4 --tag=4.1.0.4',
		'  php ocn/language/build.php 4.1.0.4 --tag 4.1.0.4',
		'Без версии читается константа VERSION из upload/index.php ветки OPENCART_BRANCH.',
		'Без --copy и --tag собирается только zip в dist/, дерево пакета не меняется.',
		'--copy кладёт ru-ru в ocn_language_russian.ocmod и обновляет version в install.json и composer.json, без коммита.',
		'--tag подразумевает --copy, затем коммит и аннотированный тег. Zip в git не добавляется, если его там ещё нет. Push не выполняется.',
	]);
}

function parseBuildArgs(array $argv): array {
	$args = array_slice($argv, 1);

	if (in_array('-h', $args, true) || in_array('--help', $args, true)) {
		fwrite(STDOUT, buildUsage() . PHP_EOL);
		exit(0);
	}

	$positional = null;
	$flagVersion = null;
	$flagVersionSeen = false;
	$tag = null;
	$tagSeen = false;
	$copy = false;

	for ($index = 0, $count = count($args); $index < $count; $index++) {
		$arg = $args[$index];

		if ($arg === '--copy') {
			if ($copy) {
				fail('Флаг --copy указан дважды.' . PHP_EOL . buildUsage());
			}

			$copy = true;
			continue;
		}

		if ($arg === '--version' || $arg === '--tag') {
			$next = $args[$index + 1] ?? null;

			if ($next === null || str_starts_with($next, '-')) {
				fail('У ' . $arg . ' нет значения.' . PHP_EOL . buildUsage());
			}

			$index++;

			if ($arg === '--version') {
				if ($flagVersionSeen) {
					fail('Версия указана дважды.' . PHP_EOL . buildUsage());
				}

				$flagVersion = $next;
				$flagVersionSeen = true;
			} else {
				if ($tagSeen) {
					fail('Тег указан дважды.' . PHP_EOL . buildUsage());
				}

				$tag = $next;
				$tagSeen = true;
			}

			continue;
		}

		if (str_starts_with($arg, '--version=')) {
			if ($flagVersionSeen) {
				fail('Версия указана дважды.' . PHP_EOL . buildUsage());
			}

			$flagVersion = substr($arg, strlen('--version='));
			$flagVersionSeen = true;
			continue;
		}

		if (str_starts_with($arg, '--tag=')) {
			if ($tagSeen) {
				fail('Тег указан дважды.' . PHP_EOL . buildUsage());
			}

			$tag = substr($arg, strlen('--tag='));
			$tagSeen = true;
			continue;
		}

		if (str_starts_with($arg, '-')) {
			fail('Неизвестный аргумент: ' . $arg . PHP_EOL . buildUsage());
		}

		if ($positional !== null) {
			fail('Ожидается один позиционный аргумент — версия.' . PHP_EOL . buildUsage());
		}

		$positional = $arg;
	}

	$version = null;

	if ($positional !== null && $flagVersionSeen) {
		$fromPositional = requireToken($positional, 'Версия');
		$fromFlag = requireToken((string)$flagVersion, 'Версия');

		if ($fromPositional !== $fromFlag) {
			fail('Позиционный аргумент и --version задают разные версии.' . PHP_EOL . buildUsage());
		}

		$version = $fromPositional;
	} elseif ($flagVersionSeen) {
		$version = requireToken((string)$flagVersion, 'Версия');
	} elseif ($positional !== null) {
		$version = requireToken($positional, 'Версия');
	}

	return [
		'version' => $version,
		'tag'     => $tagSeen ? requireToken((string)$tag, 'Тег') : null,
		'copy'    => $copy,
	];
}

function requireToken(string $raw, string $label): string {
	if (str_contains($raw, "\n") || str_contains($raw, "\r")) {
		fail('Параметр «' . $label . '» не должен содержать перевод строки.' . PHP_EOL . buildUsage());
	}

	$value = trim($raw);

	if ($value === '') {
		fail('Параметр «' . $label . '» не может быть пустым.' . PHP_EOL . buildUsage());
	}

	return $value;
}

function packageGitRoot(string $packageDir): string {
	$git = $packageDir . DIRECTORY_SEPARATOR . '.git';

	if (!is_dir($git) && !is_file($git)) {
		fail('Каталог пакета не является git-репозиторием: ' . $packageDir);
	}

	return $packageDir;
}

function assertPackageClean(string $packageGit): void {
	$status = runGit($packageGit, ['status', '--porcelain']);

	if (trim($status) !== '') {
		fail("Репозиторий пакета содержит посторонние изменения. Сборка с --tag остановлена, файлы не перезаписаны:\n" . rtrim($status));
	}
}

function assertTagFree(string $packageGit, string $tag): void {
	$format = runCommand(['git', 'check-ref-format', 'refs/tags/' . $tag]);

	if ($format['code'] !== 0) {
		fail('Некорректное имя тега: ' . $tag . PHP_EOL . buildUsage());
	}

	$existing = runCommand(['git', '-C', $packageGit, 'rev-parse', '-q', '--verify', 'refs/tags/' . $tag]);

	if ($existing['code'] === 0) {
		fail('Тег уже есть в репозитории пакета: ' . $tag);
	}
}

function publishPackage(string $packageGit, string $forkRepo, string $packageRoot, string $stagingDir, string $version, ?string $tag, string $zipPath): void {
	$packageGit = str_replace('\\', '/', $packageGit);
	$forkRepo = str_replace('\\', '/', $forkRepo);

	if (strtolower($packageGit) === strtolower($forkRepo)) {
		fail('PACKAGE_DIR указывает на форк OpenCart, а не на репозиторий пакета. Коммит не создан.');
	}

	if ($tag !== null) {
		assertPackageClean($packageGit);
	}

	$top = realpath(trim(runGit($packageGit, ['rev-parse', '--show-toplevel'])));
	$top = $top === false ? '' : str_replace('\\', '/', $top);

	if ($top === '' || strtolower($top) !== strtolower($packageGit)) {
		fail('Каталог пакета не является корнем своего git-репозитория: ' . $packageGit);
	}

	mirrorTree($stagingDir, $packageRoot);
	updateComposerVersion($packageGit . '/composer.json', $version);

	$zips = trackedLanguageZips($packageGit);

	foreach ($zips as $relativeZip) {
		$destination = $packageGit . '/' . $relativeZip;
		$directory = dirname($destination);

		if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
			fail('Не удалось создать каталог ' . $directory);
		}

		if (!copy($zipPath, $destination)) {
			fail('Не удалось скопировать zip в пакет: ' . $relativeZip);
		}
	}

	if ($tag === null) {
		fwrite(STDOUT, 'Дерево пакета обновлено, коммит не создан.' . PHP_EOL);
		return;
	}

	$relativeRoot = relativeTo($packageGit, $packageRoot);
	$add = ['add', '-A', '--', $relativeRoot, 'composer.json'];

	foreach ($zips as $relativeZip) {
		$add[] = $relativeZip;
	}

	runGit($packageGit, $add);

	$staged = runGit($packageGit, ['diff', '--cached', '--name-only', '-z']);

	foreach (explode("\0", $staged) as $path) {
		if ($path === '') {
			continue;
		}

		$normalized = str_replace('\\', '/', $path);

		if (basename($normalized) === '.env') {
			runGit($packageGit, ['reset', '-q']);
			fail('Отказ: в коммит пакета попал бы .env. Индекс сброшен, коммит не создан.');
		}
	}

	$status = runGit($packageGit, ['status', '--porcelain']);

	if (trim($status) === '') {
		fail('В репозитории пакета нет изменений для коммита. Тег не создан.');
	}

	runGit($packageGit, ['commit', '-m', $version]);
	runGit($packageGit, ['tag', '-a', $tag, '-m', $version]);

	fwrite(STDOUT, 'Коммит пакета: ' . $version . PHP_EOL);
	fwrite(STDOUT, 'Тег пакета: ' . $tag . PHP_EOL);

	if ($zips !== []) {
		fwrite(STDOUT, 'Zip в пакете: ' . implode(', ', $zips) . PHP_EOL);
	}
}

function trackedLanguageZips(string $packageGit): array {
	$listed = runGit($packageGit, ['ls-files', '-z']);
	$paths = [];

	foreach (explode("\0", $listed) as $path) {
		$normalized = str_replace('\\', '/', $path);

		if ($normalized !== '' && str_ends_with($normalized, 'ocn_language_russian.ocmod.zip')) {
			$paths[] = $normalized;
		}
	}

	return $paths;
}

function updateComposerVersion(string $path, string $version): void {
	if (!is_file($path)) {
		return;
	}

	$raw = file_get_contents($path);

	if ($raw === false) {
		fail('Не удалось прочитать ' . $path);
	}

	$data = json_decode($raw, true);

	if (!is_array($data) || !array_key_exists('version', $data)) {
		return;
	}

	if (!is_string($data['version'])) {
		fail('Поле version в composer.json не является строкой: ' . $path);
	}

	$encoded = json_encode($version, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	if ($encoded === false) {
		fail('Не удалось закодировать версию для composer.json');
	}

	$count = 0;
	$updated = preg_replace(
		'/"version"\s*:\s*"(?:[^"\\\\]|\\\\.)*"/',
		'"version": ' . $encoded,
		$raw,
		1,
		$count
	);

	if (!is_string($updated) || $count !== 1) {
		fail('Не удалось обновить version в ' . $path);
	}

	writeFile($path, $updated);
}

function mirrorTree(string $source, string $dest): void {
	$expected = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $item) {
		if (!$item->isFile()) {
			continue;
		}

		$relative = relativeTo($source, $item->getPathname());

		if ($relative === '.env' || str_ends_with($relative, '/.env')) {
			fail('Сборка содержит .env, в пакет он не копируется: ' . $relative);
		}

		copyInto($dest, $relative, $item->getPathname());
		$expected[$relative] = true;
	}

	$existing = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	$remove = [];

	foreach ($existing as $item) {
		$relative = relativeTo($dest, $item->getPathname());

		if ($relative === '.git' || str_starts_with($relative, '.git/')) {
			continue;
		}

		if ($item->isFile() && !isset($expected[$relative])) {
			$remove[] = $item->getPathname();
		}
	}

	foreach ($remove as $path) {
		if (is_file($path) && !unlink($path)) {
			fail('Не удалось удалить устаревший файл пакета: ' . $path);
		}
	}

	$directories = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($directories as $item) {
		if (!$item->isDir()) {
			continue;
		}

		$entries = scandir($item->getPathname());

		if ($entries !== false && count($entries) === 2) {
			rmdir($item->getPathname());
		}
	}
}

function runGit(string $repo, array $args): string {
	$result = runCommand(array_merge(['git', '-C', $repo, '-c', 'core.autocrlf=false'], $args), $repo);

	if ($result['code'] !== 0) {
		fail('git завершился с кодом ' . $result['code'] . ': git ' . implode(' ', $args) . "\n" . trim($result['stderr']));
	}

	return $result['stdout'];
}

function runCommand(array $command, ?string $cwd = null): array {
	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];
	$process = proc_open($command, $descriptors, $pipes, $cwd);

	if (!is_resource($process)) {
		fail('Не удалось запустить: ' . implode(' ', $command));
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return [
		'code'   => proc_close($process),
		'stdout' => $stdout === false ? '' : $stdout,
		'stderr' => $stderr === false ? '' : $stderr,
	];
}

function assertInside(string $parent, string $child): void {
	$parent = strtolower(str_replace('\\', '/', rtrim($parent, '/\\')));
	$child = strtolower(str_replace('\\', '/', $child));

	if (!str_starts_with($child, $parent . '/')) {
		fail('Отказ: путь вне каталога скрипта: ' . $child);
	}
}

function removeTree(string $dir): void {
	if (!file_exists($dir)) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $item) {
		if ($item->isDir()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}

	rmdir($dir);
}

function copySkeleton(string $packageRoot, string $stagingDir): int {
	$count = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($packageRoot, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $item) {
		if (!$item->isFile()) {
			continue;
		}

		$relative = relativeTo($packageRoot, $item->getPathname());

		if (shouldSkipSkeleton($relative)) {
			continue;
		}

		copyInto($stagingDir, $relative, $item->getPathname());
		$count++;
	}

	if ($count === 0) {
		fail('Каркас расширения пуст: ' . $packageRoot);
	}

	return $count;
}

function shouldSkipSkeleton(string $relative): bool {
	$relative = str_replace('\\', '/', $relative);

	foreach (explode('/', $relative) as $part) {
		if ($part === '.git' || $part === '.idea' || $part === 'vendor') {
			return true;
		}
	}

	if ($relative === 'install.json') {
		return true;
	}

	if ($relative === 'admin/language/ru-ru/language/russian.php') {
		return false;
	}

	if (str_starts_with($relative, 'admin/language/ru-ru/') || str_starts_with($relative, 'catalog/language/ru-ru/')) {
		return true;
	}

	return false;
}

function copyPhrases(string $extractDir, string $stagingDir, array $archivePaths): array {
	$prefixes = [];

	foreach ($archivePaths as $from => $to) {
		$prefixes[rtrim(str_replace('\\', '/', $from), '/') . '/'] = rtrim(str_replace('\\', '/', $to), '/') . '/';
	}

	uksort($prefixes, static function (string $a, string $b): int {
		return strlen($b) <=> strlen($a);
	});

	$copied = 0;
	$skipped = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $item) {
		if (!$item->isFile()) {
			continue;
		}

		$relative = relativeTo($extractDir, $item->getPathname());
		$destination = null;

		foreach ($prefixes as $from => $to) {
			if (str_starts_with($relative, $from)) {
				$destination = $to . substr($relative, strlen($from));
				break;
			}
		}

		if ($destination === null) {
			continue;
		}

		if (preg_match('#(^|/)language/ru/#', $destination)) {
			fail('В переводе осталась папка ru/: ' . $destination);
		}

		if (shouldSkipTranslation($relative, $item->getPathname())) {
			$skipped++;
			continue;
		}

		if ($destination === 'admin/language/ru-ru/language/russian.php') {
			$skipped++;
			continue;
		}

		copyInto($stagingDir, $destination, $item->getPathname());
		$copied++;
	}

	if ($copied === 0) {
		fail('Из git archive не скопировано ни одного файла перевода. Проверьте ветку и пути upload/**/language/ru-ru.');
	}

	foreach ($prefixes as $destinationPrefix) {
		$found = false;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($stagingDir, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $item) {
			if (!$item->isFile()) {
				continue;
			}

			$relative = relativeTo($stagingDir, $item->getPathname());

			if (str_starts_with($relative, $destinationPrefix)) {
				$found = true;
				break;
			}
		}

		if (!$found) {
			fail('После перекладки нет файлов в ' . $destinationPrefix);
		}
	}

	return ['copied' => $copied, 'skipped' => $skipped];
}

function shouldSkipTranslation(string $relative, string $absolute): bool {
	$relative = str_replace('\\', '/', $relative);
	$name = basename($relative);

	if (preg_match('#(^|/)install(/|$)#', $relative)) {
		return true;
	}

	if (preg_match('#(^|/)en-gb(/|$)#', $relative)) {
		return true;
	}

	if (preg_match('/^oc_.+_example(\..*)?$/i', $name)) {
		return true;
	}

	if (str_ends_with(strtolower($name), '.php')) {
		$content = file_get_contents($absolute);

		if ($content === false || !str_contains($content, '$_[')) {
			return true;
		}
	}

	return false;
}

function copyInto(string $stagingDir, string $relative, string $source): void {
	$relative = str_replace('\\', '/', $relative);

	if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
		fail('Некорректный путь назначения: ' . $relative);
	}

	$destination = $stagingDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
	$directory = dirname($destination);

	if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
		fail('Не удалось создать каталог ' . $directory);
	}

	if (!copy($source, $destination)) {
		fail('Не удалось скопировать ' . $source);
	}
}

function relativeTo(string $root, string $path): string {
	$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
	$path = str_replace('\\', '/', $path);

	if (!str_starts_with($path, $root)) {
		fail('Путь вне корня: ' . $path);
	}

	return substr($path, strlen($root));
}

function encodeInstall(array $data): string {
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	if ($json === false) {
		fail('Не удалось закодировать install.json');
	}

	$json = preg_replace_callback('/^(?:    )+/m', static function (array $match): string {
		return str_repeat('  ', intdiv(strlen($match[0]), 4));
	}, $json);

	return $json . "\n";
}

function writeFile(string $path, string $contents): void {
	if (file_put_contents($path, $contents) === false) {
		fail('Не удалось записать ' . $path);
	}
}

function extractTar(string $tar, string $dest): void {
	try {
		$phar = new PharData($tar);
		$phar->extractTo($dest, null, true);
		unset($phar);

		return;
	} catch (Throwable $exception) {
		$pharError = $exception->getMessage();
	}

	$result = runCommand(['tar', '-xf', $tar, '-C', $dest]);

	if ($result['code'] !== 0) {
		fail("Не удалось распаковать git archive.\nPhar: " . $pharError . "\ntar: " . trim($result['stderr']));
	}
}

function writeZip(string $stagingDir, string $zipPath): void {
	if (class_exists(ZipArchive::class)) {
		$zip = new ZipArchive();
		$opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		if ($opened !== true) {
			fail('Не удалось создать zip, код ' . $opened);
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($stagingDir, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $item) {
			if (!$item->isFile()) {
				continue;
			}

			$relative = relativeTo($stagingDir, $item->getPathname());

			if (!$zip->addFile($item->getPathname(), $relative)) {
				fail('Не удалось добавить в zip ' . $relative);
			}
		}

		if (!$zip->close()) {
			fail('Не удалось закрыть zip');
		}

		return;
	}

	$builder = new ZipBuilder();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($stagingDir, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $item) {
		if (!$item->isFile()) {
			continue;
		}

		$relative = relativeTo($stagingDir, $item->getPathname());
		$contents = file_get_contents($item->getPathname());

		if ($contents === false) {
			fail('Не удалось прочитать ' . $item->getPathname());
		}

		$builder->addFile($relative, $contents);
	}

	$builder->save($zipPath);
}

function verifyZip(string $zipPath, string $repo, string $branch, string $packageRoot, string $version): void {
	$required = [
		'install.json',
		'admin/controller/language/russian.php',
		'admin/model/language/russian.php',
		'admin/view/template/language/russian.twig',
		'admin/language/en-gb/language/russian.php',
		'admin/language/ru-ru/language/russian.php',
		'admin/language/ru-ru/catalog/product.php',
	];
	$entries = zipEntries($zipPath);

	foreach ($required as $name) {
		if (!in_array($name, $entries, true)) {
			fail('В zip нет ' . $name);
		}
	}

	foreach ($entries as $name) {
		if (str_starts_with($name, 'upload/') || $name === 'upload') {
			fail('В корне zip не должно быть upload/: ' . $name);
		}

		if (preg_match('#(^|/)language/ru/#', $name)) {
			fail('В zip есть папка ru/: ' . $name);
		}
	}

	$install = zipRead($zipPath, 'install.json');
	$decoded = json_decode($install, true);

	if (!is_array($decoded) || ($decoded['version'] ?? '') !== $version) {
		fail('version в install.json архива не равна ' . $version);
	}

	$productFromGit = runGit($repo, ['show', $branch . ':upload/admin/language/ru-ru/catalog/product.php']);
	$productFromZip = zipRead($zipPath, 'admin/language/ru-ru/catalog/product.php');

	if ($productFromZip !== $productFromGit) {
		fail('admin/language/ru-ru/catalog/product.php в архиве не совпадает с веткой ' . $branch);
	}

	$registration = file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'ru-ru' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'russian.php');
	$registrationFromZip = zipRead($zipPath, 'admin/language/ru-ru/language/russian.php');

	if ($registration === false || $registrationFromZip !== $registration) {
		fail('Файл регистрации языка в архиве не совпадает с каркасом пакета');
	}
}

function zipEntries(string $path): array {
	return array_keys(zipCatalog($path));
}

function zipRead(string $path, string $name): string {
	$catalog = zipCatalog($path);

	if (!isset($catalog[$name])) {
		fail('В zip нет ' . $name);
	}

	$entry = $catalog[$name];
	$handle = fopen($path, 'rb');

	if ($handle === false) {
		fail('Не удалось открыть ' . $path);
	}

	fseek($handle, $entry['offset']);
	$header = fread($handle, 30);

	if ($header === false || strlen($header) !== 30) {
		fclose($handle);
		fail('Повреждён локальный заголовок zip: ' . $name);
	}

	$local = unpack('Vsig/vver/vflag/vmethod/vtime/vdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen', $header);

	if ($local['sig'] !== 0x04034b50) {
		fclose($handle);
		fail('Неверная сигнатура локального заголовка: ' . $name);
	}

	fseek($handle, $entry['offset'] + 30 + $local['nameLen'] + $local['extraLen']);
	$payload = fread($handle, $entry['compSize']);
	fclose($handle);

	if ($payload === false || strlen($payload) !== $entry['compSize']) {
		fail('Не удалось прочитать данные zip: ' . $name);
	}

	if ($entry['method'] === 0) {
		$data = $payload;
	} elseif ($entry['method'] === 8) {
		$data = gzinflate($payload);

		if ($data === false) {
			fail('Не удалось распаковать ' . $name);
		}
	} else {
		fail('Неподдерживаемый метод сжатия ' . $entry['method'] . ' у ' . $name);
	}

	if (strlen($data) !== $entry['size']) {
		fail('Размер ' . $name . ' в zip не сходится');
	}

	return $data;
}

function zipCatalog(string $path): array {
	$handle = fopen($path, 'rb');

	if ($handle === false) {
		fail('Не удалось открыть ' . $path);
	}

	$size = filesize($path);

	if ($size === false || $size < 22) {
		fclose($handle);
		fail('Слишком маленький zip: ' . $path);
	}

	$scan = min($size, 22 + 65535);
	fseek($handle, -$scan, SEEK_END);
	$tail = fread($handle, $scan);

	if ($tail === false) {
		fclose($handle);
		fail('Не удалось прочитать конец zip');
	}

	$position = strrpos($tail, "PK\x05\x06");

	if ($position === false) {
		fclose($handle);
		fail('В zip нет EOCD');
	}

	$eocd = unpack('Vsig/vdisk/vcdDisk/vdiskEntries/vtotal/VcdSize/VcdOffset/vcomment', substr($tail, $position, 22));
	fseek($handle, $eocd['cdOffset']);
	$central = fread($handle, $eocd['cdSize']);
	fclose($handle);

	if ($central === false || strlen($central) !== $eocd['cdSize']) {
		fail('Не удалось прочитать central directory');
	}

	$entries = [];
	$offset = 0;

	for ($index = 0; $index < $eocd['total']; $index++) {
		$header = unpack(
			'Vsig/vver/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen/vcommentLen/vdisk/vinternal/Vexternal/Vlocal',
			substr($central, $offset, 46)
		);

		if ($header === false || $header['sig'] !== 0x02014b50) {
			fail('Повреждён central directory');
		}

		$name = substr($central, $offset + 46, $header['nameLen']);
		$entries[$name] = [
			'method'   => $header['method'],
			'compSize' => $header['comp'],
			'size'     => $header['uncomp'],
			'offset'   => $header['local'],
		];
		$offset += 46 + $header['nameLen'] + $header['extraLen'] + $header['commentLen'];
	}

	return $entries;
}

final class ZipBuilder {
	/** @var list<array{name:string,method:int,crc:int,size:int,compSize:int,data:string,offset:int}> */
	private array $files = [];

	public function addFile(string $name, string $contents): void {
		$name = str_replace('\\', '/', $name);

		if (str_contains($name, '..') || str_starts_with($name, '/')) {
			fail('Некорректное имя в zip: ' . $name);
		}

		$size = strlen($contents);
		$deflated = gzdeflate($contents, 9);

		if ($deflated === false || strlen($deflated) >= $size) {
			$method = 0;
			$stored = $contents;
		} else {
			$method = 8;
			$stored = $deflated;
		}

		$this->files[] = [
			'name'     => $name,
			'method'   => $method,
			'crc'      => crc32($contents),
			'size'     => $size,
			'compSize' => strlen($stored),
			'data'     => $stored,
			'offset'   => 0,
		];
	}

	public function save(string $path): void {
		$directory = dirname($path);

		if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
			fail('Не удалось создать ' . $directory);
		}

		$now = getdate();
		$dosTime = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
		$dosDate = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];
		$local = '';
		$central = '';
		$offset = 0;

		foreach ($this->files as $index => $file) {
			$nameLen = strlen($file['name']);
			$header = pack(
				'VvvvvvVVVvv',
				0x04034b50,
				20,
				0,
				$file['method'],
				$dosTime,
				$dosDate,
				$file['crc'],
				$file['compSize'],
				$file['size'],
				$nameLen,
				0
			);
			$this->files[$index]['offset'] = $offset;
			$local .= $header . $file['name'] . $file['data'];
			$offset += strlen($header) + $nameLen + $file['compSize'];
			$central .= pack(
				'VvvvvvvVVVvvvvvVV',
				0x02014b50,
				20,
				20,
				0,
				$file['method'],
				$dosTime,
				$dosDate,
				$file['crc'],
				$file['compSize'],
				$file['size'],
				$nameLen,
				0,
				0,
				0,
				0,
				0100644 << 16,
				$this->files[$index]['offset']
			);
			$central .= $file['name'];
		}

		if ($offset !== strlen($local)) {
			fail('Внутренняя ошибка сборки zip: смещение не сходится');
		}

		$eocd = pack(
			'VvvvvVVv',
			0x06054b50,
			0,
			0,
			count($this->files),
			count($this->files),
			strlen($central),
			$offset,
			0
		);

		if (file_put_contents($path, $local . $central . $eocd) === false) {
			fail('Не удалось записать ' . $path);
		}
	}
}
