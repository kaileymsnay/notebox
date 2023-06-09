<?php
/**
 *
 * Note Box extension for the phpBB Forum Software package
 *
 * @copyright (c) 2021, Kailey Snay, https://www.snayhomelab.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace kaileymsnay\notebox\core;

use phpbb\db\driver\driver_interface;

/**
 * Class bbcodes_installer
 */
class bbcodes_installer
{
	/** @var driver_interface */
	protected $db;

	/** @var */
	protected $phpbb_root_path;

	/** @var */
	protected $php_ext;

	/** @var */
	protected $acp_bbcodes;

	/**
	 * Constructor
	 *
	 * @param driver_interface  $db
	 * @param                   $phpbb_root_path
	 * @param                   $php_ext
	 */
	public function __construct(driver_interface $db, $phpbb_root_path, $php_ext)
	{
		$this->db = $db;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		$this->acp_bbcodes = $this->get_acp_bbcodes();
	}

	/**
	 * Installs bbcodes, used by migrations to perform add/updates
	 */
	public function install_bbcodes(array $bbcodes)
	{
		foreach ($bbcodes as $bbcode_name => $bbcode_data)
		{
			$bbcode_data = $this->build_bbcode($bbcode_data);

			if ($bbcode = $this->bbcode_exists($bbcode_name, $bbcode_data['bbcode_tag']))
			{
				$this->update_bbcode($bbcode, $bbcode_data);
			}
			else
			{
				$this->add_bbcode($bbcode_data);
			}
		}
	}

	/**
	 * Get the acp_bbcodes class
	 */
	protected function get_acp_bbcodes()
	{
		if (!class_exists('acp_bbcodes'))
		{
			include($this->phpbb_root_path . 'includes/acp/acp_bbcodes.' . $this->php_ext);
		}

		return new \acp_bbcodes();
	}

	/**
	 * Build the bbcode
	 */
	protected function build_bbcode(array $bbcode_data)
	{
		$data = $this->acp_bbcodes->build_regexp($bbcode_data['bbcode_match'], $bbcode_data['bbcode_tpl']);

		$bbcode_data += [
			'bbcode_tag'			=> $data['bbcode_tag'],
			'first_pass_match'		=> $data['first_pass_match'],
			'first_pass_replace'	=> $data['first_pass_replace'],
			'second_pass_match'		=> $data['second_pass_match'],
			'second_pass_replace'	=> $data['second_pass_replace'],
		];

		return $bbcode_data;
	}

	/**
	 * Get the max bbcode id value
	 */
	protected function get_max_bbcode_id()
	{
		return $this->get_max_column_value('bbcode_id');
	}

	/**
	 * Retrieve the maximum value in a column from the bbcodes table
	 */
	private function get_max_column_value($column)
	{
		$sql = 'SELECT MAX(' . $this->db->sql_escape($column) . ') AS maximum
			FROM ' . BBCODES_TABLE;
		$result = $this->db->sql_query($sql);
		$maximum = $this->db->sql_fetchfield('maximum');
		$this->db->sql_freeresult($result);

		return (int) $maximum;
	}

	/**
	 * Check if bbcode exists
	 */
	protected function bbcode_exists($bbcode_name, $bbcode_tag)
	{
		$sql = 'SELECT bbcode_id
			FROM ' . BBCODES_TABLE . "
			WHERE LOWER(bbcode_tag) = '" . strtolower($this->db->sql_escape($bbcode_name)) . "'
				OR LOWER(bbcode_tag) = '" . strtolower($this->db->sql_escape($bbcode_tag)) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	/**
	 * Update existing bbcode
	 */
	protected function update_bbcode(array $old_bbcode, array $new_bbcode)
	{
		$sql = 'UPDATE ' . BBCODES_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $new_bbcode) . '
			WHERE bbcode_id = ' . (int) $old_bbcode['bbcode_id'];
		$this->db->sql_query($sql);
	}

	/**
	 * Add new bbcode
	 */
	protected function add_bbcode(array $bbcode_data)
	{
		$bbcode_id = $this->get_max_bbcode_id() + 1;

		if ($bbcode_id <= NUM_CORE_BBCODES)
		{
			$bbcode_id = NUM_CORE_BBCODES + 1;
		}

		if ($bbcode_id <= BBCODE_LIMIT)
		{
			$bbcode_data['bbcode_id'] = (int) $bbcode_id;

			// Set display_on_posting to 1 by default, so if 0 is desired, set it in our data array
			$bbcode_data['display_on_posting'] = (int) !isset($bbcode_data['display_on_posting']);

			$this->db->sql_query('INSERT INTO ' . BBCODES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $bbcode_data));
		}
	}
}
