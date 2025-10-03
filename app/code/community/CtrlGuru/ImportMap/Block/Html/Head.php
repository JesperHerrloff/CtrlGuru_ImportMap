<?php

class CtrlGuru_ImportMap_Block_Html_Head extends Mage_Page_Block_Html_Head
{
    /**
     * Get HEAD HTML with CSS/JS/RSS definitions
     * (actually it also renders other elements, TODO: fix it up or rename this method).
     *
     * @return string
     */
    public function getCssJsHtml()
    {
        // separate items by types
        $lines = [];
        foreach ($this->_data['items'] as $item) {
            if (!is_null($item['cond'])
                && !$this->getData($item['cond'])
                || !isset($item['name'])
            ) {
                continue;
            }
            $if = !empty($item['if']) ? $item['if'] : '';
            $params = !empty($item['params']) ? $item['params'] : '';

            switch ($item['type']) {
                case 'js':        // js/*.js
                case 'skin_js':   // skin/*/*.js
                case 'js_css':    // js/*.css
                case 'skin_css':  // skin/*/*.css
                    $lines[$if][$item['type']][$params][$item['name']] = $item['name'];

                    break;

                default:
                    $this->_separateOtherHtmlHeadElements(
                        $lines,
                        $if,
                        $item['type'],
                        $params,
                        $item['name'],
                        $item
                    );

                    break;
            }
        }

        // prepare HTML
        $shouldMergeJs = Mage::getStoreConfigFlag('dev/js/merge_files');
        $shouldMergeCss = Mage::getStoreConfigFlag('dev/css/merge_css_files');
        // "External import maps are not yet supported."
        $externalImportMap = Mage::getStoreConfigFlag('dev/import_map/external');
        $html = '';
        $html .= $this->_prepareImportMap(!$externalImportMap)."\n";
        foreach ($lines as $if => $items) {
            if (empty($items)) {
                continue;
            }
            if (!empty($if)) {
                // @deprecated
                continue;
            }

            // static and skin css
            $html .= $this->_prepareStaticAndSkinElements(
                '<link rel="stylesheet" href="%s"%s >'.PHP_EOL,
                empty($items['js_css']) ? [] : $items['js_css'],
                empty($items['skin_css']) ? [] : $items['skin_css'],
                $shouldMergeCss ? [Mage::getDesign(), 'getMergedCssUrl'] : null,
            );

            // static and skin javascripts
            $html .= $this->_prepareStaticAndSkinElements(
                '<script src="%s"%s></script>'.PHP_EOL,
                empty($items['js']) ? [] : $items['js'],
                empty($items['skin_js']) ? [] : $items['skin_js'],
                $shouldMergeJs ? [Mage::getDesign(), 'getMergedJsUrl'] : null,
            );

            // other stuff
            if (!empty($items['other'])) {
                $html .= $this->_prepareOtherHtmlHeadElements($items['other']).PHP_EOL;
            }
        }

        return $html;
    }
    // Import Maps

    /**
     * @param string      $name          A unique name for the import map found in a static path (e.g. "main")
     * @param string      $path          The path to the import map file to be merged (relative to the /js/ directory - e.g. bundle/importmap.json)
     * @param null|string $devPath       An alternative file to use in developer mode
     * @param string      $referenceName The name of the item to insert the element before. If name is not found, insert at the end, * has special meaning (all)
     * @param bool        $before        If true insert before the $referenceName instead of after
     *
     * @return $this
     */
    public function addStaticImportMap(
        $name,
        $path,
        $devPath = null,
        $referenceName = '*',
        $before = false
    ) {
        $this->_data['imports']['static_import_map'][$name] = Mage::getIsDeveloperMode() && $devPath
        ? $devPath : $path;
        $this->addItem('static_import_map', $name, null, null, null, $referenceName, $before);

        return $this;
    }

    /**
     * @param string      $name          A unique name for the import map found in a skin path (e.g. "main")
     * @param string      $path          The path to the import map file to be merged (relative to the skin path - e.g. js/importmap.json)
     * @param null|string $devPath       An alternative file to use in developer mode
     * @param string      $referenceName The name of the item to insert the element before. If name is not found, insert at the end, * has special meaning (all)
     * @param bool        $before        If true insert before the $referenceName instead of after
     *
     * @return $this
     */
    public function addSkinImportMap(
        $name,
        $path,
        $devPath = null,
        $referenceName = '*',
        $before = false
    ) {
        $this->_data['imports']['skin_import_map'][$name] = Mage::getIsDeveloperMode() && $devPath
        ? $devPath : $path;
        $this->addItem('skin_import_map', $name, null, null, null, $referenceName, $before);

        return $this;
    }

    /**
     * @param string      $specifier     The specifier to use when importing the resource (e.g. "vue")
     * @param string      $fileOrUrl     The path to the file (relative to the /js/ directory) or a CDN url
     * @param null|string $devFileOrUrl  An alternative file or url to use in developer mode
     * @param string      $referenceName The name of the item to insert the element before. If name is not found, insert at the end, * has special meaning (all)
     * @param bool        $before        If true insert before the $referenceName instead of after
     *
     * @return $this
     */
    public function addStaticImport(
        $specifier,
        $fileOrUrl,
        $devFileOrUrl = null,
        $referenceName = '*',
        $before = false
    ) {
        $this->_data['imports']['static_import'][$specifier] = Mage::getIsDeveloperMode()
            && $devFileOrUrl ? $devFileOrUrl : $fileOrUrl;
        $this->addItem('static_import', $specifier, null, null, null, $referenceName, $before);

        return $this;
    }

    /**
     * @param string      $specifier     The specifier to use when importing the resource (e.g. "vue")
     * @param string      $fileOrUrl     The path to the file (relative to skin) or a CDN url
     * @param null|string $devFileOrUrl  An alternative file or url to use in developer mode
     * @param string      $referenceName The name of the item to insert the element before. If name is not found, insert at the end, * has special meaning (all)
     * @param bool        $before        If true insert before the $referenceName instead of after
     *
     * @return $this
     */
    public function addSkinImport(
        $specifier,
        $fileOrUrl,
        $devFileOrUrl = null,
        $referenceName = '*',
        $before = false
    ) {
        $this->_data['imports']['skin_import'][$specifier] = Mage::getIsDeveloperMode()
            && $devFileOrUrl ? $devFileOrUrl : $fileOrUrl;
        $this->addItem('skin_import', $specifier, null, null, null, $referenceName, $before);

        return $this;
    }

    /**
     * @throws JsonException
     */
    public function _prepareImportMap(bool $inline): string
    {
        return Mage::getSingleton('core/design_package')->renderImportMap(
            $this->_data['items'],
            $this->_data['imports'] ?? [],
            $inline
        );
    }
}
