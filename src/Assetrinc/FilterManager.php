<?php
/*
 * Assetrinc source code
 *
 * Copyright Matt Light <matt.light@lightdatasys.com>
 *
 * For copyright and licensing information, please view the LICENSE
 * that is distributed with this source code.
 */

namespace Assetrinc;

use Exception;

use Assetic\Filter\CoffeeScriptFilter;
use Assetic\Filter\CssRewriteFilter;
use Assetic\Filter\UglifyCssFilter;
use Assetic\Filter\UglifyJs2Filter;
use Assetic\Filter\ScssphpFilter;

class FilterManager
{
    private $options;
    private $filters              = array();
    private $filters_by_extension = array();

    public function __construct(array $options = null)
    {
        if (empty($options)) {
            $options = array();
        }

        if (empty($options['filters']['node_modules']['path'])) {
            $options['filters']['node_modules']['path']
                = __DIR__ . "/../../../../../../node_modules";
        }

        $node_modules = $options['filters']['node_modules']['path'];
        $options      = array_replace_recursive(
            array(
                'debug'        => false,
                'node_modules' => array(
                    'binaries' => array(
                        'coffee'     => "{{NODE_MODULES}}/coffee-script/bin/coffee",
                        'uglify_js'  => "{{NODE_MODULES}}/uglify-js/bin/uglifyjs",
                        'uglify_css' => "{{NODE_MODULES}}/uglifycss/uglifycss",
                    ),
                ),
            ),
            $options
        );

        if (!isset($options['filters']['by_extension'])) {
            $options['filters']['by_extension'] = array();
        }
        $options['filters']['by_extension'] = array_replace(
            array(
                'coffee' => array(
                    'coffee',
                ),
                'js' => array(
                    '?uglify_js',
                ),
                'scss' => array(
                    'scssphp',
                ),
                'css' => array(
                    'css_urls',
                    '?uglify_css',
                ),
            ),
            $options['filters']['by_extension']
        );

        $this->initFilterFactories($options);

        $this->options = $options;
    }

    private function initFilterFactories(array $options)
    {
        if (empty($options['filter_factories'])) {
            $options['filter_factories'] = array();
        }

        $this->filter_factories = array_replace(
            array(
                'coffee'     => function ($options) {
                    $binaries = $options['node_modules']['binaries'];

                    return new CoffeeScriptFilter($binaries['coffee']);
                },
                'css_urls'   => function ($options) {
                    return new CssRewriteFilter();
                },
                'uglify_js'  => function ($options) {
                    $binaries = $options['node_modules']['binaries'];

                    return new UglifyJs2Filter($binaries['uglify_js']);
                },
                'uglify_css' => function ($options) {
                    $binaries = $options['node_modules']['binaries'];

                    return new UglifyCssFilter($binaries['uglify_css']);
                },
                'scssphp' => function ($options) {
                    return new ScssphpFilter();
                },
            ),
            $options['filter_factories']
        );
    }

    private function getFilter($name)
    {
        if (array_key_exists($name, $this->filters)) {
            return $this->filters[$name];
        }

        if (!isset($this->filter_factories[$name])) {
            throw new Exception("Unknown filter named '{$name}'.");
        }

        if (isset($this->options['node_modules']['binaries'])) {
            $binaries = &$this->options['node_modules']['binaries'];
            foreach ($binaries as $filter_name => $binary_path) {
                $binaries[$filter_name] = str_replace(
                    '{{NODE_MODULES}}',
                    $this->options['filters']['node_modules']['path'],
                    $binary_path
                );
            }
        }

        $filter_factory       = $this->filter_factories[$name];
        $this->filters[$name] = $filter_factory($this->options);

        return $this->filters[$name];
    }

    public function getFiltersByExtension($extension)
    {
        if (array_key_exists($extension, $this->filters_by_extension)) {
            return $this->filters_by_extension[$extension];
        }

        if (!isset($this->options['filters']['by_extension'][$extension])) {
            $this->filters_by_extension[$extension] = array();

            return $this->filters_by_extension[$extension];
        }

        $this->filters_by_extension[$extension] = array();
        foreach ($this->options['filters']['by_extension'][$extension] as $filter_name) {
            $filter = null;
            if (substr($filter_name, 0, 1) === '?') {
                if (!$this->options['debug']) {
                    $filter = $this->getFilter(substr($filter_name, 1));
                }
            } else {
                $filter = $this->getFilter($filter_name);
            }

            if ($filter) {
                $this->filters_by_extension[$extension][$filter_name] = $filter;
            }
        }

        return $this->filters_by_extension[$extension];
    }
}
