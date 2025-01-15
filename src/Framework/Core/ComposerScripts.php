<?php

namespace Framework\Core;

use Composer\Script\Event;

class ComposerScripts
{
    /**
     * Handle the post-autoload-dump Composer event.
     *
     * @param  \Composer\Script\Event  $event
     * @return void
     */
    public static function postAutoloadDump(Event $event)
    {
        $vendorDir = dirname($event->getComposer()->getConfig()->get('vendor-dir'));
        $coreConfigDir = $vendorDir . '/vendor/ephraim-mago/api-core/config';
        $skeletonConfigDir = $vendorDir . '/config';

        if (is_dir($coreConfigDir)) {
            // Créer le dossier config du skeleton s'il n'existe pas
            if (!is_dir($skeletonConfigDir)) {
                mkdir($skeletonConfigDir, 0755, true);
            }

            // Copier les fichiers du core vers le skeleton
            foreach (scandir($coreConfigDir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $source = $coreConfigDir . '/' . $file;
                $destination = $skeletonConfigDir . '/' . $file;

                if (is_file($source)) {
                    copy($source, $destination);
                    echo "Copied $file to $skeletonConfigDir\n";
                }
            }
        } else {
            echo "Core config directory not found: $coreConfigDir\n";
        }
    }
}
