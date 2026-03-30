<?php
/**
 * DokuWiki Plugin kanboardsync (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
* @author  Thomas Schäfer <thomas@hilbershome.de>
 */

use dokuwiki\Extension\SyntaxPlugin;

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}
if (!defined('DOKU_PLUGIN')) {
    define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
}

class syntax_plugin_kanboardsync extends SyntaxPlugin {
    
    private $kanboarduserlink = 'KANBOARD_USERTASKS_LINK';
    private $kanboardusertasklist = 'KANBOARD_USERTASK_LIST';
        
    /**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType()
    {
        return 'normal';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        return 1;
    }
    
    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{'.$this->kanboarduserlink.'>{0,1}.*?}}', $mode, 'plugin_kanboardsync');
        $this->Lexer->addSpecialPattern('{{'.$this->kanboardusertasklist.'>{0,1}.*?}}', $mode, 'plugin_kanboardsync');
    }

    /**
     * Handle matches of the odtsupport syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {    
        if (preg_match('/{{([a-zA-Z0-9_]+)/', $match, $matches) !== 1) {
            return array();
        }
        // Der erste Match sollte genau ein Wort sein, das nach '{{' im String $match steht.
        $command = $matches[1];
        
        $param1 = "";
        $param2 = "";
        
        $article_id = null;
        $commandtype = null;
                
        switch (strtoupper($command)) {
            case $this->kanboarduserlink:
                //$kanboarduserlink_parameter = substr($match, strlen($this->kanboarduserlink)+3, -2); //strip markup

                $article_id = pageinfo()['id'];
                break;
            case $this->kanboardusertasklist:
                //$kanboarduserlink_parameter = substr($match, strlen($this->kanboarduserlink)+3, -2); //strip markup

                $article_id = pageinfo()['id'];
                break;
        }
        
        return array($command,$commandtype,$article_id,$param1,$param2);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        list($command,$commandtype,$article_id,$param1,$param2) = $data;
                                
        //$including_article_id = pageinfo()['id']; // in case of this being content of a page being included via include plugin, pageinfo() doesn't give the ID of the content being rendered, but its including page's ID - for this reason, $article_id has been extracted within the handle() function. That's possible, because pageinfo() fetches the ID of the page being included, but only within handle().
		
        $cssclass = "wikilink2";
        if (@file_exists(wikiFN($article_id))) $cssclass = "wikilink1";
        
        // get kanboard helper
        $kanboardsync_helper = $this->loadHelper('kanboardsync');

        if (is_null($kanboardsync_helper)) {
            $renderer->doc .= "<strong>Fehler:</strong> KanboardSync Helper konnte nicht geladen werden.";
            return true;
        }

                // determine last part of article id (in case of namespaced articles)
        $article_id_parts = explode(":", $article_id);
        $pagename = end($article_id_parts);
        $pagetitle = p_get_metadata($article_id)['title'];

        if ($mode == 'xhtml') {
            
            $open_tasks = $kanboardsync_helper->getOpenTasksByAssignee($pagename);
            $cssclass = "urlextern";

            //expire the page's xhtml cache to have the following code always have effect - it's presentation can depend on external factors so that the page should be updated, but a cached version would be delivered, instead. The following code can be used to achieve expiring the cache, according to: https://www.dokuwiki.org/devel:caching#plugins
            p_set_metadata($article_id, array('cache'=>'expire'),false,false);
            
            if ($command == $this->kanboarduserlink) {

                $kanboard_url = $this->getConf('kanboard_url');
                $kanboard_user_tasks_url = $kanboard_url . "?controller=TaskListController&action=show&plugin=&project_id=".$this->getConf('project_id')."&search=status%3Aopen+assignee%3A%22".$pagename."%22";
                $renderer->doc .= "<a class='".$cssclass."' href='".$kanboard_user_tasks_url."'>Offene Aufgaben im Kanboard</a> (".strval(sizeof($open_tasks)).")";
            } else if ($command == $this->kanboardusertasklist) {

                if (count($open_tasks) == 0) {
                    $renderer->doc .= "<p>Es sind keine offenen Aufgaben im Kanboard für <strong>".$pagetitle."</strong> vorhanden.</p>";
                } else {
                    $renderer->doc .= "<table><tr><th>Aufgabe im Kanboard</th><th>Aufgabenbeschreibung</th></tr>";
                    foreach ($open_tasks as $task) {
                        $renderer->doc .= "<tr>";
                        $kanboard_url = rtrim($this->getConf('kanboard_url'), '/');
                        $task_url = $kanboard_url . "/task/" . $task->id;
                        $tasks_dokuwiki_pageid = $kanboardsync_helper->getDokuwikiPageIDFromTask($task->id);
                        $internallink = "Nicht ermittelbar";
                        // if $tasks_dokuwiki_pageid is not an empty string
                        if (strlen($tasks_dokuwiki_pageid) > 0) {
                            $internallink = $renderer->internallink($tasks_dokuwiki_pageid, null, null, true);
                        }
                        $renderer->doc .= "<td><a class='".$cssclass."' href='".$task_url."'>".$task->title."</a> (ID: ".$task->id.")</td>";
                        $renderer->doc .= "<td>".$internallink."</td>";
                        $renderer->doc .= "</tr>";
                    }
                    $renderer->doc .= "</table>";
                }
            }
            return true;
        } elseif ($mode == 'text') {
            // nothing to output (hence nothing will be searchable)
            return false;
        } elseif ($mode == 'odt') {
                
            if ($command == $this->kanboarduserlink) {
                // TODO: output for ODT files
            }

            return false;
        }

        return true;
    }
}