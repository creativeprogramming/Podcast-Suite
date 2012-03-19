<?php
/**
 * @author      Joseph LeBlanc - Cory Webb Media
 * @link        www.corywebbmedia.com
 * @copyright   Copyright 2012 Cory Webb Media. All Rights Reserved.
 * @category    cwm_podcast
 * @package
 */
defined( '_JEXEC' ) or die;

class PodcastTableEpisode extends JTable
{
	public function __construct(&$db)
	{
		parent::__construct('#__podcast_episodes', 'episode_id', $db);
	}

	public function check()
	{
		if (trim($this->alias) == '') {
			$this->alias = $this->episode_title;
		}

		$this->alias = JApplication::stringURLSafe($this->alias);

		if (trim(str_replace('-', '', $this->alias)) == '') {
			$this->alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}

		// Backfill the GUID on new records
		if (!$this->episode_id) {
			$this->episode_guid = md5(microtime() . $this->episode_title . $this->episode_subtitle . $this->feed_id);
		}

		// backfill creation date on new records to today
		if (!$this->episode_created) {
			$this->episode_created = JFactory::getDate()->toSql();
		}

		// backfill publish date on new records to today
		if (!$this->episode_pubDate) {
			$this->episode_pubDate = JFactory::getDate()->toSql();
		}

		return true;
	}
}