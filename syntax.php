<?php
/**
 * DokuWiki Plugin kanboardsync (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
* @author  Thomas Schäfer <thomas@hilbershome.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}
if (!defined('DOKU_PLUGIN')) {
    define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
}

class syntax_plugin_kanboardsync extends DokuWiki_Syntax_Plugin {
    
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

        if ($mode == 'xhtml') {
            
            if ($command == $this->kanboarduserlink) {

                // determine last part of article id (in case of namespaced articles)
                $article_id_parts = explode(":", $article_id);
                $pagename = end($article_id_parts);

                $open_tasks = $kanboardsync_helper->getOpenTasksByAssignee($pagename);

                //expire the page's xhtml cache to have the following code always have effect - it's presentation can depend on external factors so that the page should be updated, but a cached version would be delivered, instead. The following code can be used to achieve expiring the cache, according to: https://www.dokuwiki.org/devel:caching#plugins
                p_set_metadata($article_id, array('cache'=>'expire'),false,false);
                
                // param1: username / pagename
                // param2: array of open tasks assigned to the user

                $cssclass = "urlextern";
                $kanboard_url = $this->getConf('kanboard_url');
                $kanboard_user_tasks_url = $kanboard_url . "?controller=TaskListController&action=show&plugin=&project_id=1&search=status%3Aopen+assignee%3A%22".$pagename."%22";
                $renderer->doc .= "<a class='".$cssclass."' href='".$kanboard_user_tasks_url."'>Offene Aufgaben im Kanboard</a> (".strval(sizeof($open_tasks)).")";
            } else if ($command == $this->kanboardusertasklist) {

                // determine last part of article id (in case of namespaced articles)
                $article_id_parts = explode(":", $article_id);
                $pagename = end($article_id_parts);

                $open_tasks = $kanboardsync_helper->getOpenTasksByAssignee($pagename);

                //expire the page's xhtml cache to have the following code always have effect - it's presentation can depend on external factors so that the page should be updated, but a cached version would be delivered, instead. The following code can be used to achieve expiring the cache, according to: https://www.dokuwiki.org/devel:caching#plugins
                p_set_metadata($article_id, array('cache'=>'expire'),false,false);
                
                // param1: username / pagename
                // param2: array of open tasks assigned to the user

                if (count($open_tasks) == 0) {
                    $renderer->doc .= "<p>Es sind keine offenen Aufgaben im Kanboard für <strong>".$pagename."</strong> vorhanden.</p>";
                } else {
                    $renderer->doc .= "<ul>";
                    foreach ($open_tasks as $task) {
                        $kanboard_url = rtrim($this->getConf('kanboard_url'), '/');
                        $task_url = $kanboard_url . "/task/" . $task->id;
                        $renderer->doc .= "<li><a class='urlextern' href='".$task_url."'>".$task->title."</a> (ID: ".$task->id.")</li>";
                    }
                    $renderer->doc .= "</ul>";
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