<?php
defined( '_JEXEC' ) or die;

jimport('joomla.application.component.model');
jimport('podcast.helper');

class PodcastModelMigrate extends JModel
{
	public $path;
	protected $old_config;
	protected $old_db;
	protected $old_params;

	public function get_old_joomla_config()
	{
		if (!isset($this->old_config)) {
			$path = JPath::clean($this->path);

			$old_config = file_get_contents($path . '/configuration.php');
			$old_config = JString::str_ireplace('JConfig', 'JConfigold', $old_config);
			$old_config = JString::str_ireplace('<?php', '', $old_config);

			// Yes, I know. eval() is evil. Goats have been sacrificed to bring
			// you this code. I promise not to use it again for a long, long time.
			eval($old_config);

			$this->old_config = new JConfigold;
		}

		return $this->old_config;
	}

	public function get_old_joomla_db()
	{
		if (!isset($this->old_db)) {
			jimport('joomla.database.database.mysql');

			$config = $this->get_old_joomla_config();

			$this->old_db = JDatabase::getInstance(array(
				'host' => $config->host,
				'user' => $config->user,
				'password' => $config->password,
				'database' => $config->db,
				'prefix' => $config->dbprefix
			));
		}

		return $this->old_db;
	}

	public function import_feeds()
	{
		$params = $this->_get_old_podcast_params();

		$row = JTable::getInstance('feed', 'PodcastTable');
		$row->feed_title = $params->title;
		$row->feed_link = JURI::root(); // not defined in earlier versions, may want to change this
		$row->feed_language = JFactory::getLanguage()->getDefault(); // not defined earlier, defaulting to default
		$row->feed_copyright = $params->copyright;
		$row->feed_subtitle = $params->itSubtitle;
		$row->feed_author = $params->itAuthor;
		$row->feed_block = $params->itBlock;
		$row->feed_explicit = $params->itExplicit;
		$row->feed_keywords = $params->itKeywords;
		$row->feed_summary = $params->description;
		$row->feed_owner_name = $params->itOwnerName;
		$row->feed_owner_email = $params->itOwnerEmail;
		$row->feed_image = $params->itImage;
		$row->feed_category1 = $params->itCategory1;
		$row->feed_category2 = $params->itCategory2;
		$row->feed_category3 = $params->itCategory3;

		if (!$this->_get_latest_feed_id()) {
			$row->feed_default = 1;
		}

		return $row->store();
	}

	public function import_podcast_episodes()
	{
		$rows = $this->_get_old_podcast_records();
		$feed_id = $this->_get_latest_feed_id();
		$enclosures = $this->_get_old_podcast_enclosures();

		foreach ($rows as $row) {
			$newrow = JTable::getInstance('episode', 'PodcastTable');
			$newrow->feed_id = $feed_id;
			$newrow->episode_title = $enclosures[$row->filename]['row']->title;
			$newrow->episode_author = $row->itAuthor;
			$newrow->episode_subtitle = $row->itSubtitle;
			$newrow->episode_summary = $this->_clean_introtext($enclosures[$row->filename]['row']->introtext);
			$newrow->episode_pubDate = $enclosures[$row->filename]['row']->publish_up;
			$newrow->episode_keywords = $row->itKeywords;
			$newrow->episode_created = $enclosures[$row->filename]['row']->created;
			$newrow->episode_block = $row->itBlock;
			$newrow->published = $enclosures[$row->filename]['row']->state;
		}

	}

	public function import_podcast_assets()
	{
		$rows = $this->_get_old_podcast_records();
		$enclosures = $this->_get_old_podcast_enclosures();
		$params = $this->_get_old_podcast_params();

		$old_media_path = $this->path . '/' . $params->get('mediapath') . '/';

		$folder = PodcastHelper::getOptions()->get('folder', '/media/podcasts/');

		foreach ($rows as $row) {

			$newrow = JTable::getInstance('asset', 'PodcastTable');

			if (stripos($row->filename, 'http') === 0) {
				$newrow->asset_enclosure_url = $row->filename;

				if (isset($enclosures[$row->filename])) {
					$newrow->asset_enclosure_length = $enclosures[$row->filename]['length'];
					$newrow->asset_enclosure_type = $enclosures[$row->filename]['mime'];
				}

			} else {
				$file_info = $this->_get_file_info($old_media_path . $row->filename);
				$newrow->asset_enclosure_url = $folder . $row->filename;
				$newrow->asset_enclosure_length = $file_info['length'];
				$newrow->asset_enclosure_type = $file_info['type'];
				$newrow->storage_engine = 'local';
			}

			$newrow->asset_duration = $row->itDuration;
		}
	}

	private function _get_file_info($filepath)
	{
		jimport('getid3.getid3.getid3');

        $getid3 = new getID3;
        $info = $getid3->analyze($filepath);

		$parsed = array();

		$parsed['length'] = (isset($info['filesize']) ? $info['filesize'] : 0);
        $parsed['type'] = (isset($info['mime_type']) ? $info['mime_type'] : '');
        $parsed['duration'] = (isset($info['playtime_string']) ? $info['playtime_string'] : '');

        return $parsed;
	}

	private function _get_old_podcast_records()
	{
		$db = $this->get_old_joomla_db();

		$query = $db->getQuery(true);

		$query->select("*")->from("#__podcast");

		$db->setQuery($query);
		return $db->loadObjectList();
	}

	private function _get_old_podcast_enclosures()
	{
		$db = $this->get_old_joomla_db();

		$query = $db->getQuery(true);

		$query->select("*")
			->from("#__content")
			->where("introtext LIKE '%{enclose %'");

		$db->setQuery($query);
		$content = $db->loadObjectList();

		$enclosures = array();

		foreach ($content as $item) {
			if (preg_match('/\{enclose (.*)\}/', $item->introtext, $matches)) {
				$enclose_pieces = explode(' ', $matches[0]);

				$info = array('content_id' => $item->id);

				if (count($enclose_pieces) > 1) {
 					$info = array(
						'file' => $enclose_pieces[0],
						'length' => $enclose_pieces[1],
						'mime' => $enclose_pieces[2]
					);
				}

				$info['row'] = $item;

				$enclose_pieces[0] = $info;
			}
		}

		return $enclosures;
	}

	private function _get_old_podcast_params()
	{
		if (!isset($this->old_params)) {
			$db = $this->get_old_joomla_db();

			$query = $db->getQuery(true);

			$query->select("params")
					->from("#__components")
					->where("link = 'option=com_podcast'");

			$db->setQuery($query);
			$params = $db->loadResult();

			jimport('joomla.registry.format');
			$formatter = JRegistryFormat::getInstance('INI');
			$this->old_params = $formatter->stringToObject($params);
		}

		return $this->old_params;
	}

	/**
	 * NOTE: This function currently returns the latest ID from the feeds
	 * table. This entire function may need to be replaced with a more
	 * sophisticated method of matching multiple import feeds to episodes.
	 *
	 * @return int
	 */
	private function _get_latest_feed_id()
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true);

		$query->select('max(feed_id)')
			->from('#__podcast_feeds');

		$db->setQuery($query);

		return $db->loadResult();
	}

	// TODO: this should remove {enclose} tags
	private function _clean_introtext($text)
	{
		return $text;
	}
}