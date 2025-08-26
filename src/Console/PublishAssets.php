<?php namespace Seiger\sMultisite\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Class PublishAssets
 *
 * Prunes outdated published assets and republishes package files.
 * - Deletes specific target files before publish
 * - Calls vendor:publish for this provider
 */
class PublishAssets extends Command
{
    /** @var string */
    protected $signature = 'smultisite:publish {--no-prune : Do not delete existing files before publish}';

    /** @var string */
    protected $description = 'Publish sMultisite assets (with optional prune).';

    public function handle(Filesystem $fs): int
    {
        // 1) Targets to delete before publishing
        $targets = [
            public_path('assets/site/smultisite.min.css'),
            public_path('assets/site/smultisite.js'),
        ];

        if (!$this->option('no-prune')) {
            foreach ($targets as $path) {
                // File::delete() is safe even if file does not exist
                $fs->delete($path);
            }
        }

        // 2) Publish (force overwrite)
        $this->call('vendor:publish', [
            '--provider' => 'Seiger\sMultisite\sMultisiteServiceProvider',
        ]);

        // 3) (Optional) drop VERSION file for debugging
        try {
            $ver = \Composer\InstalledVersions::getVersion('seiger/smultisite');
            $fs->ensureDirectoryExists(public_path('assets/site'));
            $fs->put(
                public_path('core/vendor/seiger/smultisite/config/sMultisiteCheck.php'),
                "<?php return ['check_sMultisite' => true, 'sMultisiteVer' => '" . $ver . "'];"
            );
        } catch (\Throwable) {
            // ignore if class not available
        }

        $this->info('sMultisite assets published.');
        return self::SUCCESS;
    }
}
