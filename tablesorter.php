<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class TablesorterPlugin
 * @package Grav\Plugin
 */
class TablesorterPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
        ]);
    }

    public function onPageInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $defaults = (array) $this->config->get('plugins.tablesorter');
        /** @var Page $page */
        $page = $this->grav['page'];
        if (isset($page->header()->tablesorter)) {
            $this->config->set('plugins.tablesorter', array_merge($defaults, $page->header()->tablesorter));
        }
        if ($this->config->get('plugins.tablesorter.active')) {
            $this->enable([
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
                'onOutputGenerated' => ['onOutputGenerated', 0]
            ]);
        }
    }

    public function onTwigSiteVariables()
    {
        $defaults = (array) $this->config->get('plugins.tablesorter');
        /** @var Page $page */
        $page = $this->grav['page'];
        if (isset($page->header()->tablesorter)) {
            $this->config->set('plugins.tablesorter', array_merge($defaults, $page->header()->tablesorter));
        }

        $mode = $this->config->get('plugins.tablesorter.production') ? '.min' : '';

        $bits = [];
        // Add core js
        $bits[] = 'plugin://tablesorter/dist/js/jquery.tablesorter'.$mode.'.js';

        // Add metadata
        if ($this->config->get('plugins.tablesorter.include_metadata')) {
            $bits[] = 'plugin://tablesorter/dist/js/extras/jquery.metadata'.$mode.'.js';
        }

        // Add widgets
        if ($this->config->get('plugins.tablesorter.include_widgets')) {
            $bits[] = 'plugin://tablesorter/dist/js/jquery.tablesorter.widgets'.$mode.'.js';
        }

        // Add theme css
        $themes = $this->config->get('plugins.tablesorter.themes');
        if ($themes !== null) {
            $themes = str_replace(' ', '', $themes);
            $themes = explode(',', $themes);
            foreach ($themes as $theme) {
                $bits[] = 'plugin://tablesorter/dist/css/theme.'.$theme.$mode.'.css';
            }
        }

        // Add the bits
        $assets = $this->grav['assets'];
        $assets->registerCollection('tablesorter', $bits);
        $assets->add('tablesorter', 100);

        // Insert inline JS code
        //   Get table numbers
        $nums = $this->config->get('plugins.tablesorter.table_nums');
        if ($nums !== null) {
            // strip space characters
            $nums = str_replace(' ', '', $nums);
            // explode on the comma
            $nums = explode(',', $nums);

            //inject execution code
            $code = [];
            $code[] = '$(function(){';
            $templatecode = '$("#TABLEID").tablesorter(ARGS);';
            $args = $this->config->get('plugins.tablesorter.args');
            foreach ($nums as $num) {
                $codestr = $templatecode;
                $codestr = str_replace('TABLEID', 'tstableid'.$num, $codestr);
                if ($args !== null) {
                    if (array_key_exists($num, $args)) {
                        $params = $args[$num];
                        if (! isset($params['theme'])) {
                            $params['theme'] = $this->config->get('plugins.tablesorter.themes');
                        }
                        $codestr = str_replace('ARGS', json_encode($params), $codestr);
                    } else {
                        $params = $args;
                        if (! isset($params['theme'])) {
                            $params['theme'] = $this->config->get('plugins.tablesorter.themes');
                        }
                        $codestr = str_replace('ARGS', json_encode($params), $codestr);
                    }
                } else {
                    $params = [];
                    $params['theme'] = $this->config->get('plugins.tablesorter.themes');
                    $codestr = str_replace('ARGS', json_encode($params), $codestr);
                }
                $code[] = $codestr;
            }
            $code[] = '})';
            $code = implode('', $code);
            $assets->addInlineJs($code);
        }
    }

    public function onOutputGenerated(Event $e)
    {
        $config = $this->grav['config'];

        $nums = $config->get('plugins.tablesorter.table_nums');
        if ($nums !== null) {
            // strip space characters
            $nums = str_replace(' ', '', $nums);
            // explode on the comma
            $nums = explode(',', $nums);

            $content = $this->grav->output;
            // Get count of <table> tags in the output
            $tblcount = substr_count($content, '<table');
            $offset = 0;
            for ($i=1; $i<=$tblcount; $i++) {
                // Get pos of first <table> tag
                $pos = strpos($content, '<table', $offset);
                $str1 = substr($content, 0, $pos);
                $str2 = substr($content, $pos);
                // Are we supposed to touch this table?
                if (in_array($i, $nums)) {
                    // Get full first <table> tag
                    preg_match('/\<table.*?\>/', $str2, $matches);
                    $fulltag = $matches[0];

                    // add ID tag
                    if (strpos($fulltag, 'id=') !== false) {
                        $fulltag = str_replace('id="', 'id="tstableid'.$i.' ', $fulltag);
                    } else {
                        $fulltag = str_replace('<table', '<table id="tstableid'.$i.'"', $fulltag);
                    }

                    // add class
                    if (strpos($fulltag, 'class=') !== false) {
                        $fulltag = str_replace('class="', 'class="tablesorter ', $fulltag);
                    } else {
                        $fulltag = str_replace('>', ' class="tablesorter">', $fulltag);
                    }

                    // replace existing <table> tag with modified one
                    $str2 = preg_replace('/'.preg_quote($matches[0]).'/', $fulltag, $str2, 1);
                    $content = $str1.$str2;
                }
                // move offset
                $offset = strpos($content, '<table', $offset) + 1;
            }
            $this->grav->output = $content;
        }
    }
}
