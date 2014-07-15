<?php
/**
*
* @package Meta Tags phpBB SEO
* @version $$
* @copyright (c) 2014 www.phpbb-seo.com
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbseo\meta\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	* Current $phpbb_root_path
	* @var string
	*/
	protected $phpbb_root_path;

	/**
	* Current $php_ext
	* @var string
	*/
	protected $php_ext;

	/* Limit in chars for the last post link text. */
	protected $char_limit = 25;

	/* Since we actually need the usu and migration depends on does not fully enforce rules */
	protected $can_actually_run = false;

	protected $filters = array('description' => 'meta_filter_txt', 'keywords' => 'make_keywords');

	/**
	* Constructor
	*
	* @param \phpbb\config\config				$config				Config object
	* @param \phpbb\template\template			$template			Template object
	* @param \phpbb\user						$user				User object
	* @param \phpbb\db\driver\driver_interface	$db					Database object
	* @param string								$phpbb_root_path	Path to the phpBB root
	* @param string								$php_ext			PHP file extension
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\db\driver\driver_interface $db, $phpbb_root_path, $php_ext)
	{
		global $phpbb_container; // god save the hax
		$this->config = $config;
		$this->can_actually_run = !empty($this->config['seo_meta_on']);

		if ($this->can_actually_run)
		{
			$this->user = $user;
			$this->db = $db;
			$this->template = $template;
			$this->phpbb_root_path = $phpbb_root_path;
			$this->php_ext = $php_ext;

			// default values, leave empty to only output the corresponding tag if filled
			\phpbbseo\meta\core::$meta_def['robots'] = 'index,follow';

			// global values, if these are empty, the corresponding meta will not show up
			\phpbbseo\meta\core::$meta['category'] = 'general';
			\phpbbseo\meta\core::$meta['distribution'] = 'global';
			\phpbbseo\meta\core::$meta['resource-type'] = 'document';

			// other settings that may be set through acp in case the mod is not used standalone
			if (isset($this->config['seo_meta_desc_limit']))
			{
				// defaults
				\phpbbseo\meta\core::$meta_def['title'] = $this->config['seo_meta_title'];
				\phpbbseo\meta\core::$meta_def['description'] = $this->config['seo_meta_desc'];
				\phpbbseo\meta\core::$meta_def['keywords'] = $this->config['seo_meta_keywords'];
				\phpbbseo\meta\core::$meta_def['robots'] = $this->config['seo_meta_robots'];

				// global
				\phpbbseo\meta\core::$meta['lang'] = $this->config['seo_meta_lang'];
				\phpbbseo\meta\core::$meta['copyright'] = $this->config['seo_meta_copy'];

				// settings
				\phpbbseo\meta\core::$config['wordlimit'] = (int) $this->config['seo_meta_desc_limit'];
				\phpbbseo\meta\core::$config['keywordlimit'] = (int) $this->config['seo_meta_keywords_limit'];
				\phpbbseo\meta\core::$config['wordminlen'] = (int) $this->config['seo_meta_min_len'];
				\phpbbseo\meta\core::$config['check_ignore'] = (int) $this->config['seo_meta_check_ignore'];
				\phpbbseo\meta\core::$config['file_filter'] = preg_replace('`[\s]+`', '', trim($this->config['seo_meta_file_filter'], ', '));
				\phpbbseo\meta\core::$config['get_filter'] = preg_replace('`[\s]+`', '', trim($this->config['seo_meta_get_filter'], ', '));
				\phpbbseo\meta\core::$config['bbcodestrip'] = str_replace(',', '|', preg_replace('`[\s]+`', '', trim($this->config['seo_meta_bbcode_filter'], ', ')));
			}
			else
			{
				// default values, leave empty to only output the corresponding tag if filled
				\phpbbseo\meta\core::$meta_def['title'] = $this->config['sitename'];
				\phpbbseo\meta\core::$meta_def['description'] = $this->config['site_desc'];
				\phpbbseo\meta\core::$meta_def['keywords'] = $this->config['site_desc'];

				// global values, if these are empty, the corresponding meta will not show up
				\phpbbseo\meta\core::$meta['lang'] = $this->config['default_lang'];
				\phpbbseo\meta\core::$meta['copyright'] = $this->config['sitename'];
			}

			\phpbbseo\meta\core::$config['get_filter'] = !empty(\phpbbseo\meta\core::$config['get_filter']) ? @explode(',', \phpbbseo\meta\core::$config['get_filter']) : array();
			\phpbbseo\meta\core::$config['topic_sql'] = $this->config['search_type'] == 'fulltext_native' ? \phpbbseo\meta\core::$config['topic_sql'] : false;
		}
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_footer'					=> 'core_page_footer',
			'core.index_modify_page_title'		=> 'core_index_modify_page_title',
			'core.viewforum_modify_topics_data'	=> 'core_viewforum_modify_topics_data',
			'core.viewtopic_modify_post_row'	=> 'core_viewtopic_modify_post_row',
		);
	}

	/**
	* assign / retrun meta tag code
	*/
	public function build_meta($page_title = '', $return = false)
	{
		// If meta robots was not manually set
		if (empty(\phpbbseo\meta\core::$meta['robots']))
		{
			// If url Rewriting is on, we shall be more strict on noindex (since we can :p)
			if (!empty(\phpbbseo\usu\core::$seo_opt['url_rewrite']))
			{
				// If url Rewriting is on, we can deny indexing for any rewritten url with ?
				if (preg_match('`(\.html?|/)\?[^\?]*$`i', \phpbbseo\usu\core::$seo_path['uri']))
				{
					\phpbbseo\meta\core::$meta['robots'] = 'noindex,follow';
				}
				else
				{
					// lets still add some more specific ones
					\phpbbseo\meta\core::$config['get_filter'] = array_merge(\phpbbseo\meta\core::$config['get_filter'], array('st','sk','sd','ch'));
				}
			}

			// Do we allow indexing based on physical script file name
			if (empty(\phpbbseo\meta\core::$meta['robots']))
			{
				if (!empty($this->user->page['page_name']) && strpos(\phpbbseo\meta\core::$config['file_filter'], str_replace(".$this->php_ext", '', $this->user->page['page_name'])) !== false)
				{
					\phpbbseo\meta\core::$meta['robots'] = 'noindex,follow';
				}
			}

			// Do we allow indexing based on get variable
			if (empty(\phpbbseo\meta\core::$meta['robots']))
			{
				foreach ( \phpbbseo\meta\core::$config['get_filter'] as $get )
				{
					if (isset($_GET[$get]))
					{
						\phpbbseo\meta\core::$meta['robots'] = 'noindex,follow';
						break;
					}
				}
			}

			// fallback to default if necessary
			if (empty(\phpbbseo\meta\core::$meta['robots']))
			{
				\phpbbseo\meta\core::$meta['robots'] = \phpbbseo\meta\core::$meta_def['robots'];
			}
		}

		if (!empty($this->config['seo_meta_noarchive']))
		{
			$forum_id = isset($_GET['f']) ? max(0, request_var('f', 0)) : 0;

			if ($forum_id)
			{
				$forum_ids = @explode(',', preg_replace('`[\s]+`', '', trim($this->config['seo_meta_noarchive'], ', ')));

				if (in_array($forum_id, $forum_ids))
				{
					\phpbbseo\meta\core::$meta['robots'] .= (!empty(\phpbbseo\meta\core::$meta['robots']) ? ',' : '') . 'noarchive';
				}
			}
		}

		// deal with titles, assign the tag if a default is set
		if (empty(\phpbbseo\meta\core::$meta['title']) && !empty(\phpbbseo\meta\core::$meta_def['title']))
		{
			\phpbbseo\meta\core::$meta['title'] = $page_title;
		}

		$meta_code = '';

		foreach (\phpbbseo\meta\core::$tpl as $key => $value)
		{
			if (isset(\phpbbseo\meta\core::$meta[$key]))
			{
				// do like this so we can deactivate one particular tag on a given page,
				// by just setting the meta to an empty string
				if (trim(\phpbbseo\meta\core::$meta[$key]))
				{
					\phpbbseo\meta\core::$meta[$key] = isset($this->filters[$key]) ? $this->{$this->filters[$key]}(\phpbbseo\meta\core::$meta[$key]) : \phpbbseo\meta\core::$meta[$key];
				}
			}
			else if (!empty(\phpbbseo\meta\core::$meta_def[$key]))
			{
				\phpbbseo\meta\core::$meta[$key] = isset($this->filters[$key]) ? $this->{$this->filters[$key]}(\phpbbseo\meta\core::$meta_def[$key]) : \phpbbseo\meta\core::$meta_def[$key];
			}

			if (trim(\phpbbseo\meta\core::$meta[$key]))
			{
				$meta_code .= sprintf($value, utf8_htmlspecialchars(\phpbbseo\meta\core::$meta[$key])) . "\n";
			}
		}

		if (!$return)
		{
			$this->template->assign_var('META_TAGS', $meta_code);
		}
		else
		{
			return $meta_code;
		}
	}

	/**
	* Returns a coma separated keyword list
	*/
	public function make_keywords($text, $decode_entities = false)
	{
		// we add ’ to the num filter because it does not seems to always be cought by punct
		// and it is widely used in languages files
		static $filter = array('`&(amp;)?[^;]+;`i', '`[[:punct:]]+`', '`[0-9’]+`',  '`[\s]+`');

		$keywords = '';
		$num = 0;
		$text = $decode_entities ? html_entity_decode(strip_tags($text), ENT_COMPAT, 'UTF-8') : strip_tags($text);
		$text = utf8_strtolower(trim(preg_replace($filter, ' ', $text)));

		if (!$text)
		{
			return '';
		}

		$text = explode(' ', trim($text));
		if (\phpbbseo\meta\core::$config['check_ignore'])
		{
			// add stop words to $user to allow reuse
			if (empty($this->user->stop_words))
			{
				$words = array();

				if (file_exists("{$this->user->lang_path}{$this->user->lang_name}/search_ignore_words.$this->php_ext"))
				{
					// include the file containing ignore words
					include("{$this->user->lang_path}{$this->user->lang_name}/search_ignore_words.$this->php_ext");
				}

				$this->user->stop_words = & $words;
			}

			$text = array_diff($text, $this->user->stop_words);
		}

		if (empty($text))
		{
			return '';
		}

		// We take the most used words first
		$text = array_count_values($text);
		arsort($text);

		foreach ($text as $word => $count)
		{
			if ( utf8_strlen($word) > \phpbbseo\meta\core::$config['wordminlen'] )
			{
				$keywords .= ', ' . $word;
				$num++;
				if ( $num >= \phpbbseo\meta\core::$config['keywordlimit'] )
				{
					break;
				}
			}
		}

		return trim($keywords, ', ');
	}

	/**
	* Filter php/html tags and white spaces and string with limit in words
	*/
	public function meta_filter_txt($text, $bbcode = true)
	{
		if ($bbcode)
		{
			static $RegEx = array();
			static $replace = array();

			if (empty($RegEx))
			{
				$RegEx = array('`&(amp;)?[^;]+;`i', // HTML entitites
					'`<[^>]*>(.*<[^>]*>)?`Usi', // HTML code
				);
				$replace = array(' ', ' ');
				if (!empty(\phpbbseo\meta\core::$config['bbcodestrip']))
				{
					$RegEx[] = '`\[(' . \phpbbseo\meta\core::$config['bbcodestrip'] . ')[^\[\]]*\].*\[/\1[^\[\]]*\]`Usi'; // bbcode to strip
					$replace[] = ' ';
				}

				$RegEx[] = '`\[\/?[a-z0-9\*\+\-]+(?:=(?:&quot;.*&quot;|[^\]]*))?(?::[a-z])?(\:[0-9a-z]{5,})\]`'; // Strip all bbcode tags
				$replace[] = '';

				$RegEx[] = '`[\s]+`'; // Multiple spaces
				$replace[] = ' ';
			}

			return $this->word_limit(preg_replace($RegEx, $replace, $text));
		}

		return $this->word_limit(preg_replace(array('`<[^>]*>(.*<[^>]*>)?`Usi', '`\[\/?[a-z0-9\*\+\-]+(?:=(?:&quot;.*&quot;|[^\]]*))?(?::[a-z])?(\:[0-9a-z]{5,})\]`', '`[\s]+`'), ' ', $text));
	}

	/**
	* Cut the text according to the number of words.
	* Borrowed from www.php.net http://www.php.net/preg_replace
	*/
	public function word_limit($string)
	{
		return count($words = preg_split('/\s+/', ltrim($string), \phpbbseo\meta\core::$config['wordlimit'] + 1)) > \phpbbseo\meta\core::$config['wordlimit'] ? rtrim(utf8_substr($string, 0, utf8_strlen($string) - utf8_strlen(end($words)))) . \phpbbseo\meta\core::$config['ellipsis'] : $string;
	}

	public function core_page_footer($event)
	{
		$this->build_meta($event['page_title']);
	}

	public function core_index_modify_page_title($event)
	{
		\phpbbseo\meta\core::collect('description', $this->config['sitename'] . ' : ' .  $this->config['site_desc']);
		\phpbbseo\meta\core::collect('keywords', $this->config['sitename'] . ' ' . \phpbbseo\meta\core::$meta['description']);
	}

	public function core_viewforum_modify_topics_data($event)
	{
		global $forum_data; // god save the hax
		\phpbbseo\meta\core::collect('description', $forum_data['forum_name'] . ' : ' . (!empty($forum_data['forum_desc']) ? $forum_data['forum_desc'] : \phpbbseo\meta\core::$meta_def['description']));
		\phpbbseo\meta\core::collect('keywords', $forum_data['forum_name'] . ' ' . \phpbbseo\meta\core::$meta['description']);
	}

	public function core_viewtopic_modify_post_row($event)
	{
		global $post_list; // god save the hax

		static $i = 0;

		if (!$this->can_actually_run)
		{
			return;
		}

		if ($event['current_row_number'] == 0)
		{
			$row = $event['row'];
			$topic_data = $event['topic_data'];
			$message = censor_text($row['post_text']);
			$m_kewrd = '';
			\phpbbseo\meta\core::collect('description', $message);
			if (\phpbbseo\meta\core::$config['topic_sql'])
			{
				$common_sql = \phpbbseo\meta\core::$config['bypass_common'] ? '' : 'AND w.word_common = 0';
				// collect keywords from all post in page
				$post_id_sql = $this->db->sql_in_set('m.post_id', $post_list, false, true);
				$sql = "SELECT w.word_text
					FROM " . SEARCH_WORDMATCH_TABLE . " m, " . SEARCH_WORDLIST_TABLE . " w
					WHERE $post_id_sql
						AND w.word_id = m.word_id
						$common_sql
					ORDER BY w.word_count DESC";
				$result = $this->db->sql_query_limit($sql, min(25, (int) \phpbbseo\meta\core::$config['keywordlimit']));
				while ( $meta_row = $this->db->sql_fetchrow($result) )
				{
					$m_kewrd .= ' ' . $meta_row['word_text'];
				}

				$this->db->sql_freeresult($result);
			}
			\phpbbseo\meta\core::collect('keywords', $topic_data['topic_title'] . ' ' . $row['post_subject'] . ' ' . (!empty($m_kewrd) ? $m_kewrd : \phpbbseo\meta\core::$meta['description']));
		}
	}
}