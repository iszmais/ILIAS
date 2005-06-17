<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


/**
* Meta Data class (element typicalagerange)
*
* @package ilias-core
* @version $Id$
*/
include_once 'class.ilMDBase.php';

class ilMDTypicalAgeRange extends ilMDBase
{
	function ilMDTypicalAgeRange($a_rbac_id = 0,$a_obj_id = 0,$a_obj_type = '')
	{
		parent::ilMDBase($a_rbac_id,
						 $a_obj_id,
						 $a_obj_type);
	}

	// SET/GET
	function setTypicalAgeRange($a_typical_age_range)
	{
		$this->typical_age_range = $a_typical_age_range;
	}
	function getTypicalAgeRange()
	{
		return $this->typical_age_range;
	}
	function setTypicalAgeRangeLanguage(&$lng_obj)
	{
		if(is_object($lng_obj))
		{
			$this->typical_age_range_language = $lng_obj;
		}
	}
	function &getTypicalAgeRangeLanguage()
	{
		return is_object($this->typical_age_range_language) ? $this->typical_age_range_language : false;
	}
	function getTypicalAgeRangeLanguageCode()
	{
		return is_object($this->typical_age_range_language) ? $this->typical_age_range_language->getLanguageCode() : false;
	}

	function setTypicalAgeRangeMinimum($a_min)
	{
		$this->typical_age_range_minimum = $a_min;
	}
	function getTypicalAgeRangeMinimum()
	{
		return $this->typical_age_range_minimum;
	}
	function setTypicalAgeRangeMaximum($a_max)
	{
		$this->typical_age_range_maximum = $a_max;
	}
	function getTypicalAgeRangeMaximum()
	{
		return $this->typical_age_range_maximum;
	}


	function save()
	{
		$this->__parseTypicalAgeRange();

		if($this->db->autoExecute('il_meta_typical_age_range',
								  $this->__getFields(),
								  DB_AUTOQUERY_INSERT))
		{
			$this->setMetaId($this->db->getLastInsertId());

			return $this->getMetaId();
		}
		return false;
	}

	function update()
	{
		$this->__parseTypicalAgeRange();

		if($this->getMetaId())
		{
			if($this->db->autoExecute('il_meta_typical_age_range',
									  $this->__getFields(),
									  DB_AUTOQUERY_UPDATE,
									  "meta_typical_age_range_id = '".$this->getMetaId()."'"))
			{
				return true;
			}
		}
		return false;
	}

	function delete()
	{
		if($this->getMetaId())
		{
			$query = "DELETE FROM il_meta_typical_age_range ".
				"WHERE meta_typical_age_range_id = '".$this->getMetaId()."'";
			
			$this->db->query($query);
			
			return true;
		}
		return false;
	}
			

	function __getFields()
	{
		return array('rbac_id'	=> $this->getRBACId(),
					 'obj_id'	=> $this->getObjId(),
					 'obj_type'	=> ilUtil::prepareDBString($this->getObjType()),
					 'parent_type' => $this->getParentType(),
					 'parent_id' => $this->getParentId(),
					 'typical_age_range'	=> ilUtil::prepareDBString($this->getTypicalAgeRange()),
					 'typical_age_range_language' => ilUtil::prepareDBString($this->getTypicalAgeRangeLanguageCode()),
					 'typical_age_range_min' => ilUtil::prepareDBString($this->getTypicalAgeRangeMinimum()),
					 'typical_age_range_max' => ilUtil::prepareDBString($this->getTypicalAgeRangeMaximum()));
	}

	function read()
	{
		include_once 'Services/MetaData/classes/class.ilMDLanguageItem.php';

		if($this->getMetaId())
		{
			$query = "SELECT * FROM il_meta_typical_age_range ".
				"WHERE meta_typical_age_range_id = '".$this->getMetaId()."'";

			$res = $this->db->query($query);
			while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
			{
				$this->setRBACId($row->rbac_id);
				$this->setObjId($row->obj_id);
				$this->setObjType($row->obj_type);
				$this->setParentId($row->parent_id);
				$this->setParentType($row->parent_type);
				$this->setTypicalAgeRange(ilUtil::stripSlashes($row->typical_age_range));
				$this->setTypicalAgeRangeLanguage(new ilMDLanguageItem($row->typical_age_range_language));
				$this->setTypicalAgeRangeMinimum($row->typical_age_range_min);
				$this->setTypicalAgeRangeMaximum($row->typical_age_range_max);
			}
		}
		return true;
	}
				
	/*
	 * XML Export of all meta data
	 * @param object (xml writer) see class.ilMD2XML.php
	 * 
	 */
	function toXML(&$writer)
	{
		$writer->xmlElement('TypicalAgeRange',array('Language' => $this->getTypicalAgeRangeLanguageCode()),$this->getTypicalAgeRange());
	}


	// STATIC
	function _getIds($a_rbac_id,$a_obj_id,$a_parent_id,$a_parent_type)
	{
		global $ilDB;

		$query = "SELECT meta_typical_age_range_id FROM il_meta_typical_age_range ".
			"WHERE rbac_id = '".$a_rbac_id."' ".
			"AND obj_id = '".$a_obj_id."' ".
			"AND parent_id = '".$a_parent_id."' ".
			"AND parent_type = '".$a_parent_type."'";

		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$ids[] = $row->meta_typical_age_range_id;
		}
		return $ids ? $ids : array();
	}

	// PRIVATE
	function __parseTypicalAgeRange()
	{
		if(preg_match("/\s*(\d*)\s*(-?)\s*(\d*)/",$this->getTypicalAgeRange(),$matches))
		{
			if(!$matches[2] and !$matches[3])
			{
				$min = $max = $matches[1];
			}
			elseif($matches[2] and !$matches[3])
			{
				$min = $matches[1];
				$max = 99;
			}
			else
			{
				$min = $matches[1];
				$max = $matches[3];
			}
			$this->setTypicalAgeRangeMaximum($max);
			$this->setTypicalAgeRangeMinimum($min);

			return true;
		}

		if(!$this->getTypicalAgeRange())
		{
			$this->setTypicalAgeRangeMinimum(-1);
			$this->setTypicalAgeRangeMaximum(-1);
		}
		return true;
	}
			
}
?>