<?php
defined( '_JEXEC' ) or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');

class JFormFieldExtraassets extends JFormField
{
	protected $type = 'extraassets';

	public function getInput()
    {
        $assets = explode(',', $this->value);
        
        $html = array();
        $html[] = '<ul id="extra_assets">';
        
        if (count($assets) > 1)
        {
            array_shift($assets);
            $db = JFactory::getDBO();
            foreach ($assets as $asset)
            {
                $query = $db->getQuery(true);
                $query->select('*')->from('#__podcast_assets')->where('asset_id = '.$asset);
                $db->setQuery($query);
                $record = $db->loadObject();
                if ($record) {
                    $html[] = '<li data-id="'.$asset.'">';
                    $html[] = 'File: '.$record->asset_enclosure_url.'<br />';
                    $html[] = 'Media Length: '.$record->asset_enclosure_length.'<br />';
                    $html[] = 'Media Duration: '.$record->asset_duration.'<br />';
                    $html[] = 'Media Type: '.$record->asset_enclosure_type.'<br />';
                    $html[] = '</li>';
                }
            }
        }
        
        $html[] = '</ul>';
        $html[] = '<input type="hidden" name="'.$this->name.'" value="['.$this->value.']" id="'.$this->id.'" />';
        
        return implode(PHP_EOL, $html);
    }
}