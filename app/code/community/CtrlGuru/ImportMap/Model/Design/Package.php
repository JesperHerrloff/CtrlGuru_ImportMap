<?php

class CtrlGuru_ImportMap_Model_Design_Package extends Mage_Core_Model_Design_Package
{
    /**
     * Remove all merged js/css files.
     *
     * @return bool
     */
    public function cleanMergedJsCss()
    {
        $result = (bool) $this->_initMergerDir('js', true);
        $result = (bool) $this->_initMergerDir('css', true) && $result;
        $result = (bool) $this->_initMergerDir('importmap', true) && $result;

        return (bool) $this->_initMergerDir('css_secure', true) && $result;
    }

    /**
     * @throws JsonException
     */
    public function renderImportMap(array $allItems, array $itemUrls, bool $inline): string
    {
        $importMap = [];
        $cacheKey = [];
        $fileHashKey = []; // No timestamps in hash key in production mode
        $useCache = !$inline && Mage::app()->useCache('import_map');
        foreach ($allItems as $item) {
            if (!preg_match('#^(static|skin)_import(_map)?$#', $item['type'])
                || !isset($itemUrls[$item['type']][$item['name']])
            ) {
                continue;
            }
            $fileOrUrl = $itemUrls[$item['type']][$item['name']];

            switch ($item['type']) {
                case 'static_import_map':
                case 'skin_import_map':
                    if ('skin_import_map' === $item['type']) {
                        $filePath = $this->getFilename($fileOrUrl, ['_type' => 'skin']);
                    } else {
                        $filePath = Mage::getBaseDir().DS.'js'.DS.$fileOrUrl;
                    }
                    $fileHashKey[] = $item['name'];
                    if (!($fileData = file_get_contents($filePath))) {
                        throw new Exception(
                            'Could not read importmap file or file is empty: '.$filePath
                        );
                    }
                    if ($useCache) {
                        $cacheKey[] = Mage::getIsDeveloperMode()
                        ? $filePath.'-'.filemtime($filePath) : $filePath;
                    }
                    $importData = json_decode($fileData, true, 3, JSON_THROW_ON_ERROR);
                    if (isset($importData['imports'])) {
                        $importMap['imports'] = array_merge(
                            $importMap['imports'] ?? [],
                            $importData['imports']
                        );
                    }
                    if (isset($importData['scopes'])) {
                        $importMap['scopes'] = array_merge(
                            $importMap['scopes'] ?? [],
                            $importData['scopes']
                        );
                    }

                    break;

                case 'static_import':
                case 'skin_import':
                    $fileHashKey[] = $fileOrUrl;
                    if (!preg_match('#^https?://#', $fileOrUrl)) {
                        if ('skin_import' === $item['type']) {
                            $fileOrUrl = $this->getSkinUrl(
                                $fileOrUrl
                            ).'?v='.filemtime($this->getFilename($fileOrUrl, ['_type' => 'skin']));
                        } else {
                            $fileOrUrl = Mage::getBaseUrl('js')
                                .$fileOrUrl
                                .'?v='.filemtime(Mage::getBaseDir().DS.'js'.DS.$fileOrUrl);
                        }
                    }
                    $importMap['imports'][$item['name']] = $fileOrUrl;

                    break;
            }
        }
        if (!$importMap) {
            return '';
        }

        $html = '';
        if ($useCache) {
            $cacheKey = md5(
                'LAYOUT_'.$this->getArea().'_STORE'.$this->getStore()->getId().'_'
                .$this->getPackageName().'_'.$this->getTheme('layout').'_'
                .(Mage::app()->getRequest()->isSecure() ? 's' : 'u').'_'.implode('|', $cacheKey)
            );
            $html = Mage::app()->loadCache($cacheKey);
        }

        // Allow devs to bypass cache in browser
        if ($useCache && Mage::getIsDeveloperMode() && isset($_SERVER['HTTP_CACHE_CONTROL'])
            && false !== strpos($_SERVER['HTTP_CACHE_CONTROL'], 'no-cache')
        ) {
            $html = null;
        }

        if (!$html) {
            if ($inline) {
                $html = '<script type="importmap">'.json_encode($importMap).'</script>';
            } else {
                $filePrefix = md5(implode('|', $fileHashKey));
                $targetFilename = $filePrefix.'.json';
                if ($this->_writeImportMap(json_encode($importMap), $targetFilename)) {
                    $url = Mage::getBaseUrl(
                        'media',
                        Mage::app()->getRequest()->isSecure()
                    ).'importmap/'.$targetFilename.'?v='.time();
                    $html = '<script type="importmap" src="'.$url.'"></script>';
                } else {
                    $html = '<script type="importmap">'.json_encode($importMap).'</script>';
                }
            }
            if ($useCache) {
                // Cache with infinite lifetime since we have filemtime in cache key in dev mode
                Mage::app()->saveCache($html, $cacheKey, ['import_map'], null);
            }
        }

        return $html;
    }

    protected function _writeImportMap(string $data, string $targetFile): bool
    {
        try {
            $targetDir = Mage::getBaseDir('media').DS.'importmap';
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir)) {
                    throw new Exception(sprintf('Could not create directory %s.', $targetDir));
                }
            }
            if (!is_writable($targetDir)) {
                throw new Exception(sprintf('Path %s is not writeable.', $targetDir));
            }
            file_put_contents($targetDir.DS.$targetFile, $data, LOCK_EX);
            if (Mage::helper('core/file_storage_database')->checkDbUsage()) {
                Mage::helper('core/file_storage_database')->saveFile($targetDir.DS.$targetFile);
            }

            return true;
        } catch (Exception $e) {
            Mage::logException($e);

            return false;
        }
    }
}
