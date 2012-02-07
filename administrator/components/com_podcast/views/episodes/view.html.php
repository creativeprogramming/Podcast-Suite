<?php
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

class PodcastViewEpisodes extends JView
{
	protected $items;
	protected $state;
	protected $pagination;

	public function display($tpl = null)
	{
		$this->items = $this->get('Items');
		$this->state = $this->get('State');
		$this->pagination = $this->get('Pagination');

		$this->addToolbar();

		parent::display($tpl);
	}

	public function addToolbar()
	{
		JToolBarHelper::title(JText::_('COM_PODCAST_EPISODES_MANAGER'));

		JToolBarHelper::publish('episodes.publish');
		JToolBarHelper::unpublish('episodes.unpublish');

		JToolBarHelper::divider();

		JToolBarHelper::deleteList('', 'episodes.delete');

		JToolBarHelper::divider();

		JToolBarHelper::editList('episodes.edit');
		JToolBarHelper::addNew('episodes.add');

		JToolBarHelper::divider();

		$bar = JToolBar::getInstance('toolbar');
        $html = '<a class="toolbar" href="http://podcastsuite.com/support" target="_blank"><span class="icon-32-help"></span>'.JText::_('JTOOLBAR_HELP').'</a>';
        $bar->appendButton('Custom', $html, 'help');
	}
}