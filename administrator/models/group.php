<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Tjfield
 * @author     Techjoomla <contact@techjoomla.com>
 * @copyright  2016  Techjoomla
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.modeladmin');

/**
 * Methods supporting a list of Tjfields records.
 *
 * @since  1.6
 *
 */

class TjfieldsModelGroup extends JModelAdmin
{
	/**
	 * @var		string	The prefix to use with controller messages.
	 * @since	1.6
	 */
	protected $text_prefix = 'COM_TJFIELDS';

	/**
	 * Returns a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JTable    A database object
	 */
	public function getTable($type = 'Group', $prefix = 'TjfieldsTable', $config = array())
	{
		JLoader::import('components.com_tjfields.tables.group', JPATH_ADMINISTRATOR);

		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional ordering field.
	 * @param   boolean  $loadData  An optional direction (asc|desc).
	 *
	 * @return  JForm    $form      A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$app	= JFactory::getApplication();

		// Get the form.
		$form = $this->loadForm('com_tjfields.group', 'group', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	$data  The data for the form.
	 *
	 * @since	1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_tjfields.edit.group.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed  $item  Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{
			// Do any procesing on fields here if needed
		}

		return $item;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param   JTable  $table  A JTable object.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function prepareTable($table)
	{
		jimport('joomla.filter.output');

		if (empty($table->id))
		{
			// Set ordering to the last item if not set
			if (@$table->ordering === '')
			{
				$db = JFactory::getDbo();
				$db->setQuery('SELECT MAX(ordering) FROM #__tjfields_groups');
				$max = $db->loadResult();
				$table->ordering = $max + 1;
			}
		}
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return   mixed		The user id on success, false on failure.
	 *
	 * @since	1.6
	 */
	public function save($data)
	{
		$table = $this->getTable();
		$input = JFactory::getApplication()->input;
		$data['name'] = trim($data['name']);
		$data['title'] = trim($data['title']);

		// Set group title as group label
		if (!empty($data['name']))
		{
			$data['title'] = $data['name'];
		}

		if ($input->get('task') == 'save2copy')
		{
			unset($data['id']);
			$name = explode("(", $data['name']);
			$name = trim($name['0']);
			$name = str_replace("`", "", $name);
			$db = JFactory::getDbo();
			$query = 'SELECT a.*'
			. ' FROM #__tjfields_groups AS a'
			. " WHERE  a.name LIKE '" . $db->escape($name) . "%'"
			. " AND  a.client LIKE '" . $db->escape($data['client']) . "'";
			$db->setQuery($query);
			$posts = $db->loadAssocList();
			$postsCount = count($posts) + 1;
			$data['name'] = $name . ' (' . $postsCount . ')';
			$data['created_by'] = JFactory::getUser()->id;
		}

		if ($table->save($data) === true)
		{
			$id = $table->id;
			$this->setState($this->getName() . '.id', $id);
			$data['fieldGroupId'] = $id;
			$client = $data['client'];

			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id');
			$query->from($db->quoteName('#__tj_ucm_types'));
			$query->where($db->quoteName('unique_identifier') . " = " . $db->quote($client));
			$db->setQuery($query);
			$typeId = $db->loadResult();

			$dispatcher = JDispatcher::getInstance();
			JPluginHelper::importPlugin('tjfield');
			$isNew = ($data['id'] != 0) ? false : true;
			$dispatcher->trigger('tjfieldOnAfterFieldGroupSave', array($data, $typeId, $isNew));

			return $id;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Method to delete one or more field groups.
	 *
	 * @param   array  &$pks  An array of record primary keys.
	 *
	 * @return  boolean  True if successful, false if an error occurs.
	 *
	 * @since   1.4.2
	 */
	public function delete(&$pks)
	{
		// Load fields and field model
		JLoader::import('components.com_tjfields.models.fields', JPATH_ADMINISTRATOR);
		JLoader::import('components.com_tjfields.models.field', JPATH_ADMINISTRATOR);

		$db = JFactory::getDbo();
		$pks = (array) $pks;

		foreach ($pks as $pk)
		{
			if (empty($pk))
			{
				continue;
			}

			// Delete fields in the field group to be deleted
			$tjFieldsFieldsModel = JModelLegacy::getInstance('Fields', 'TjfieldsModel', array('ignore_request' => true));
			$tjFieldsFieldsModel->setState("filter.group_id", $pk);
			$fields = $tjFieldsFieldsModel->getItems();

			foreach ($fields as $field)
			{
				$tjFieldsFieldModel = JModelLegacy::getInstance('Field', 'TjfieldsModel', array('ignore_request' => true));
				$status = $tjFieldsFieldModel->delete($field->id);

				if ($status === false)
				{
					return false;
				}
			}

			// Delete field group data
			if (!parent::delete($pk))
			{
				return false;
			}
		}

		return true;
	}
}
