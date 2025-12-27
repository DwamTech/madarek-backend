<?php

namespace App\Http\Controllers;

use App\Models\BackupHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * عرض قائمة النسخ الاحتياطية المتاحة.
     */
    public function index()
    {
        $backupDisk = Storage::disk('local');
        $appName = config('backup.backup.name');

        // استرجاع الملفات من مجلد النسخ الاحتياطي
        $files = $backupDisk->files($appName);

        $backups = [];
        foreach ($files as $file) {
            // تجاهل الملفات غير zip
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'zip') {
                continue;
            }

            $backups[] = [
                'file_name' => basename($file),
                'file_size' => $this->humanFileSize($backupDisk->size($file)),
                'created_at' => date('Y-m-d H:i:s', $backupDisk->lastModified($file)),
                'download_link' => route('backup.download', ['file_name' => basename($file)]), // إذا أردت استخدام رابط مباشر
            ];
        }

        // ترتيب حسب التاريخ (الأحدث أولاً)
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json($backups);
    }

    /**
     * تحميل ملف النسخة الاحتياطية.
     */
    public function download(Request $request)
    {
        $fileName = $request->input('file_name');

        if (! $fileName) {
            return response()->json(['message' => 'File name is required'], 400);
        }

        $backupDisk = Storage::disk('local');
        $appName = config('backup.backup.name');
        $filePath = $appName.'/'.$fileName;

        if (! $backupDisk->exists($filePath)) {
            // محاولة البحث في الجذر أيضاً
            if ($backupDisk->exists($fileName)) {
                $filePath = $fileName;
            } else {
                return response()->json(['message' => 'Backup file not found'], 404);
            }
        }

        $fullPath = $backupDisk->path($filePath);

        return response()->download($fullPath, $fileName);
    }

    /**
     * تحويل حجم الملف إلى صيغة مقروءة.
     */
    private function humanFileSize($bytes, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).@$sz[$factor];
    }

    /**
     * إنشاء نسخة احتياطية يدوياً.
     */
    /**
     * إنشاء نسخة احتياطية يدوياً (في الخلفية).
     */
    public function create(Request $request)
    {
        file_put_contents(storage_path('logs/debug_backup.log'), 'Create method called at '.date('Y-m-d H:i:s')."\n", FILE_APPEND);

        try {
            $mode = strtolower((string) $request->input('mode', 'full'));
            file_put_contents(storage_path('logs/debug_backup.log'), "Mode: $mode\n", FILE_APPEND);

            if (! in_array($mode, ['full', 'db'], true)) {
                file_put_contents(storage_path('logs/debug_backup.log'), "Invalid mode\n", FILE_APPEND);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid mode. Allowed values: full, db',
                ], 422);
            }

            $params = [];
            if ($mode === 'db') {
                $params['--only-db'] = true;
            }

            file_put_contents(storage_path('logs/debug_backup.log'), "Creating history record...\n", FILE_APPEND);
            // تسجيل العملية قبل البدء
            \App\Models\BackupHistory::create([
                'type' => 'create',
                'status' => 'queued',
                'file_name' => null,
                'message' => "Manual backup ({$mode}) queued.",
                'user_id' => $request->user() ? $request->user()->id : null,
            ]);
            file_put_contents(storage_path('logs/debug_backup.log'), "History record created.\n", FILE_APPEND);

            file_put_contents(storage_path('logs/debug_backup.log'), "Queueing artisan command...\n", FILE_APPEND);
            // تشغيل الأمر في الخلفية
            Artisan::queue('backup:run', $params);
            file_put_contents(storage_path('logs/debug_backup.log'), "Command queued.\n", FILE_APPEND);

            return response()->json([
                'success' => true,
                'mode' => $mode,
                'message' => 'Backup process has been queued and will run in the background.',
            ]);

        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/debug_backup.log'), 'Exception: '.$e->getMessage()."\n".$e->getTraceAsString()."\n", FILE_APPEND);
            Log::error('Backup queue failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Throwable $t) {
            file_put_contents(storage_path('logs/debug_backup.log'), 'Fatal Error: '.$t->getMessage()."\n".$t->getTraceAsString()."\n", FILE_APPEND);

            return response()->json(['success' => false, 'message' => $t->getMessage()], 500);
        }
    }

    /**
     * استرجاع نسخة احتياطية.
     */
    public function restore(Request $request)
    {
        $fileName = (string) $request->input('file_name');

        // تسجيل بدء الاسترجاع
        BackupHistory::create([
            'type' => 'restore',
            'status' => 'started',
            'file_name' => $fileName,
            'message' => 'Restore process started.',
            'user_id' => $request->user() ? $request->user()->id : null,
        ]);

        // 1. التحقق (Validation)
        if (! $fileName) {
            return response()->json(['message' => 'File name is required'], 400);
        }

        if ($fileName !== basename($fileName) || str_contains($fileName, '..') || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            return response()->json(['message' => 'Invalid file name'], 422);
        }

        // نفترض أن النسخ مخزنة في القرص المحلي 'local' داخل مجلد 'Laravel' (الاسم الافتراضي في config)
        // حسب الإعدادات السابقة: 'filename_prefix' => '' ومسار التخزين.
        // spatie/laravel-backup يخزن عادة في storage/app/Laravel (اسم التطبيق)

        // سنبحث في المسار المحدد في config/filesystems.php للقرص 'local'
        // أو نستخدم Storage::disk('local')->path(...)

        $backupDisk = Storage::disk('local');
        // اسم المجلد عادة يكون اسم التطبيق 'Laravel' أو حسب APP_NAME في .env
        $appName = config('backup.backup.name');
        $backupPath = $appName.'/'.$fileName;

        if (! $backupDisk->exists($backupPath)) {
            // محاولة البحث في الجذر إذا لم يكن في مجلد التطبيق
            if ($backupDisk->exists($fileName)) {
                $backupPath = $fileName;
            } else {
                return response()->json(['message' => 'Backup file not found'], 404);
            }
        }

        $fullPath = $backupDisk->path($backupPath);

        if (! class_exists('ZipArchive')) {
            return response()->json(['message' => 'ZipArchive extension is missing'], 500);
        }

        $enteredMaintenance = false;
        $filesSwapped = false;
        $oldPublicPath = null;

        $zip = new ZipArchive;
        $tempPath = storage_path('app/backup-temp/'.uniqid());

        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0700, true);
        }

        try {
            $preBackup = $this->createPreRestoreBackup();
            if (! $preBackup['success']) {
                $this->deleteDirectory($tempPath);

                return response()->json([
                    'message' => 'فشل إنشاء نسخة احتياطية وقائية. تم إلغاء العملية.',
                    'error' => $preBackup['message'],
                ], 500);
            }

            if (! app()->isDownForMaintenance()) {
                Artisan::call('down');
                $enteredMaintenance = true;
            }

            if ($zip->open($fullPath) !== true) {
                throw new \Exception('Failed to open zip file');
            }

            $zip->extractTo($tempPath);
            $zip->close();

            $extractedPublicPath = $tempPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
            if (! is_dir($extractedPublicPath)) {
                throw new \Exception('storage/app/public not found in the backup archive.');
            }

            $sqlFile = $this->findSqlFile($tempPath);

            if (! $sqlFile) {
                throw new \Exception('SQL dump file not found in the backup archive.');
            }

            $livePublicPath = storage_path('app/public');
            $oldPublicPath = storage_path('app/backup-temp/public-pre-restore-'.uniqid());

            if (! is_dir(dirname($oldPublicPath))) {
                mkdir(dirname($oldPublicPath), 0700, true);
            }

            if (is_dir($livePublicPath)) {
                if (! @rename($livePublicPath, $oldPublicPath)) {
                    throw new \Exception('Failed to backup current storage/app/public.');
                }
            } else {
                $oldPublicPath = null;
            }

            if (! @rename($extractedPublicPath, $livePublicPath)) {
                if ($oldPublicPath && is_dir($oldPublicPath)) {
                    @rename($oldPublicPath, $livePublicPath);
                }

                throw new \Exception('Failed to restore storage/app/public.');
            }

            $filesSwapped = true;

            $this->importDatabase($sqlFile);

            Artisan::call('optimize:clear');

            $this->deleteDirectory($tempPath);

            if ($oldPublicPath) {
                $this->deleteDirectory($oldPublicPath);
            }

            if ($enteredMaintenance) {
                Artisan::call('up');
            }

            BackupHistory::create([
                'type' => 'restore',
                'status' => 'success',
                'file_name' => $fileName,
                'message' => 'Backup restored successfully.',
                'user_id' => $request->user() ? $request->user()->id : null,
            ]);

            return response()->json(['success' => true, 'message' => 'Backup restored successfully']);
        } catch (\Exception $e) {
            if ($filesSwapped && $oldPublicPath) {
                try {
                    $livePublicPath = storage_path('app/public');
                    $this->deleteDirectory($livePublicPath);
                    @rename($oldPublicPath, $livePublicPath);
                } catch (\Exception $rollbackException) {
                    Log::error('Restore rollback failed: '.$rollbackException->getMessage());
                }
            }

            if ($enteredMaintenance) {
                try {
                    Artisan::call('up');
                } catch (\Exception $upException) {
                    Log::error('Failed to bring the app up after restore failure: '.$upException->getMessage());
                }
            }

            $this->deleteDirectory($tempPath);
            Log::error('Restore failed: '.$e->getMessage());

            BackupHistory::create([
                'type' => 'restore',
                'status' => 'failed',
                'file_name' => $fileName,
                'message' => $e->getMessage(),
                'user_id' => $request->user() ? $request->user()->id : null,
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * إنشاء نسخة احتياطية وقائية قبل الاسترجاع.
     */
    private function createPreRestoreBackup()
    {
        try {
            // نستخدم --only-db لأننا نسترجع قاعدة البيانات فقط
            Artisan::call('backup:run', ['--only-db' => true]);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Pre-restore backup failed: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * البحث عن ملف SQL في المجلدات المستخرجة.
     */
    private function findSqlFile($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'sql') {
                return $item->getPathname();
            }
        }

        return null;
    }

    /**
     * استيراد قاعدة البيانات باستخدام سطر الأوامر.
     */
    private function importDatabase($sqlPath)
    {
        $dbConfig = config('database.connections.mysql');

        $username = $dbConfig['username'];
        $password = $dbConfig['password'];
        $database = $dbConfig['database'];
        $host = $dbConfig['host'];
        $port = $dbConfig['port'];

        $dumpConfig = is_array($dbConfig) && isset($dbConfig['dump']) && is_array($dbConfig['dump']) ? $dbConfig['dump'] : [];
        $dumpBinaryPath = isset($dumpConfig['dump_binary_path']) ? (string) $dumpConfig['dump_binary_path'] : '';

        $mysqlBinary = 'mysql';
        if ($dumpBinaryPath !== '') {
            $candidateExe = rtrim($dumpBinaryPath, '\\/').DIRECTORY_SEPARATOR.'mysql.exe';
            $candidate = rtrim($dumpBinaryPath, '\\/').DIRECTORY_SEPARATOR.'mysql';
            if (is_file($candidateExe)) {
                $mysqlBinary = $candidateExe;
            } elseif (is_file($candidate)) {
                $mysqlBinary = $candidate;
            }
        }

        $tempDefaultsFile = storage_path('app/backup-temp/mysql-'.uniqid().'.cnf');
        $tempDefaultsDir = dirname($tempDefaultsFile);
        if (! is_dir($tempDefaultsDir)) {
            mkdir($tempDefaultsDir, 0700, true);
        }

        $defaults = [
            '[client]',
            'user='.$username,
            'password='.$password,
            'host='.$host,
            'port='.$port,
        ];

        if (file_put_contents($tempDefaultsFile, implode(PHP_EOL, $defaults).PHP_EOL) === false) {
            throw new \Exception('Failed to write mysql defaults file.');
        }
        @chmod($tempDefaultsFile, 0600);

        $descriptors = [
            0 => ['file', $sqlPath, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $command = escapeshellarg($mysqlBinary)
            .' --defaults-extra-file='.escapeshellarg($tempDefaultsFile)
            .' --binary-mode=1 '
            .escapeshellarg($database);

        try {
            $process = proc_open($command, $descriptors, $pipes);
            if (! is_resource($process)) {
                throw new \Exception('Failed to start mysql process.');
            }

            stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new \Exception('Database import failed: '.$stderr);
            }
        } finally {
            @unlink($tempDefaultsFile);
        }
    }

    /**
     * حذف مجلد ومحتوياته.
     */
    private function deleteDirectory($dir)
    {
        if (! file_exists($dir)) {
            return true;
        }

        if (! is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (! $this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * عرض سجل النسخ الاحتياطي.
     */
    public function history()
    {
        $history = BackupHistory::latest()->take(50)->get();

        return response()->json($history);
    }

    /**
     * رفع ملف نسخة احتياطية من مصدر خارجي.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        // التأكد من أن الامتداد zip
        if (! str_ends_with(strtolower($originalName), '.zip')) {
            return response()->json(['message' => 'File must be a zip archive.'], 422);
        }

        $backupDisk = Storage::disk('local');
        $appName = config('backup.backup.name');

        // التحقق من وجود الملف مسبقاً لمنع التكرار أو الكتابة فوقه
        $destinationPath = $appName.'/'.$originalName;

        if ($backupDisk->exists($destinationPath)) {
            return response()->json(['message' => 'A backup file with this name already exists.'], 409);
        }

        try {
            // حفظ الملف
            $backupDisk->putFileAs($appName, $file, $originalName);

            // تسجيل في السجل
            BackupHistory::create([
                'type' => 'upload',
                'status' => 'success',
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'message' => 'External backup uploaded successfully.',
                'user_id' => $request->user() ? $request->user()->id : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Backup uploaded successfully.',
                'file_name' => $originalName,
            ]);

        } catch (\Exception $e) {
            Log::error('Backup upload failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to upload backup.'], 500);
        }
    }
}
