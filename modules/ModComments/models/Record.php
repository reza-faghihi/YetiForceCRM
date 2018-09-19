<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com.
 * *********************************************************************************** */

/**
 * ModComments Record Model.
 */
class ModComments_Record_Model extends Vtiger_Record_Model
{
	/**
	 * Functions gets the comment id.
	 */
	public function getId()
	{
		$id = $this->get('modcommentsid');
		if (empty($id)) {
			return $this->get('id');
		}
		return $this->get('modcommentsid');
	}

	public function setId($id)
	{
		return $this->set('modcommentsid', $id);
	}

	/**
	 * Function returns url to get child comments.
	 *
	 * @return string - url
	 */
	public function getChildCommentsUrl()
	{
		return $this->getDetailViewUrl() . '&mode=showChildComments';
	}

	public function getImage()
	{
		$commentor = $this->getCommentedByModel();
		if ($commentor) {
			$customer = $this->get('customer');
			$isMailConverterType = $this->get('from_mailconverter');
			if (!empty($customer) && $isMailConverterType != 1) {
				$recordModel = Vtiger_Record_Model::getInstanceById($customer);
				$imageDetails = $recordModel->getImage();
				if (!empty($imageDetails)) {
					return $imageDetails;
				} else {
					return [];
				}
			} elseif ($isMailConverterType == 1) {
				return \App\Layout::getImagePath('MailConverterComment.png');
			} else {
				$imagePath = $commentor->getImage();
				if (!empty($imagePath)) {
					return $imagePath;
				}
			}
		}
		return [];
	}

	/**
	 * Prepare value to save.
	 *
	 * @return array
	 */
	public function getValuesForSave()
	{
		$forSave = parent::getValuesForSave();
		if (empty($forSave['vtiger_modcomments']['userid'])) {
			$forSave['vtiger_modcomments']['userid'] = App\User::getCurrentUserId();
		}
		return $forSave;
	}

	/**
	 * Function returns the parent Comment Model.
	 *
	 * @return Vtiger_Record_Model
	 */
	public function getParentCommentModel()
	{
		$recordId = $this->get('parent_comments');
		if (!empty($recordId)) {
			return Vtiger_Record_Model::getInstanceById($recordId, 'ModComments');
		}
		return false;
	}

	/**
	 * Function returns the parent Record Model(Contacts, Accounts etc).
	 *
	 * @return Vtiger_Record_Model
	 */
	public function getParentRecordModel()
	{
		$parentRecordId = $this->get('related_to');
		if (!empty($parentRecordId)) {
			return Vtiger_Record_Model::getInstanceById($parentRecordId);
		}
		return false;
	}

	/**
	 * Function returns the commentor Model (Users Model).
	 *
	 * @return Vtiger_Record_Model|false
	 */
	public function getCommentedByModel()
	{
		$customer = $this->get('customer');
		if (!empty($customer)) {
			return Vtiger_Record_Model::getInstanceById($customer, 'Contacts');
		} else {
			$commentedBy = $this->get('assigned_user_id');
			if ($commentedBy) {
				$commentedByModel = Vtiger_Record_Model::getInstanceById($commentedBy, 'Users');
				if (empty($commentedByModel->entity->column_fields['user_name'])) {
					$commentedByModel = Vtiger_Record_Model::getInstanceById(Users::getActiveAdminId(), 'Users');
				}
				return $commentedByModel;
			}
		}
		return false;
	}

	/**
	 * Function returns the commented time.
	 *
	 * @return string
	 */
	public function getCommentedTime()
	{
		$commentTime = $this->get('createdtime');

		return $commentTime;
	}

	/**
	 * Function returns the commented time.
	 *
	 * @return string
	 */
	public function getModifiedTime()
	{
		$commentTime = $this->get('modifiedtime');

		return $commentTime;
	}

	/**
	 * Function returns all the parent comments model.
	 *
	 * @param int                 $parentId
	 * @param int[]               $hierarchy
	 * @param Vtiger_Paging_Model $pagingModel
	 *
	 * @return \ModComments_Record_Model[]
	 */
	public static function getAllParentComments($parentId, $hierarchy = false, $pagingModel = false)
	{
		$queryGenerator = new \App\QueryGenerator('ModComments');
		$queryGenerator->setFields(['parent_comments', 'createdtime', 'modifiedtime', 'related_to', 'id',
			'assigned_user_id', 'commentcontent', 'creator', 'customer', 'reasontoedit', 'userid', 'parents']);
		$queryGenerator->setSourceRecord($parentId);
		if (empty($hierarchy) || (count($hierarchy) === 1 && reset($hierarchy) === 0)) {
			$queryGenerator->addNativeCondition(['related_to' => $parentId]);
		} else {
			$recordIds = \App\ModuleHierarchy::getRelatedRecords($parentId, $hierarchy);
			if (empty($recordIds)) {
				return [];
			}
			$queryGenerator->addNativeCondition(['related_to' => $recordIds]);
		}
		$queryGenerator->addNativeCondition(['parent_comments' => 0]);
		$query = $queryGenerator->createQuery()->orderBy(['vtiger_crmentity.createdtime' => SORT_DESC]);
		if ($pagingModel && $pagingModel->get('limit') !== 0) {
			$query->limit($pagingModel->getPageLimit())->offset($pagingModel->getStartIndex());
		}
		$dataReader = $query->createCommand()->query();
		$recordInstances = [];
		while ($row = $dataReader->read()) {
			$recordInstance = new self();
			$recordInstance->setData($row)->setModuleFromInstance($queryGenerator->getModuleModel());
			$recordInstances[] = $recordInstance;
		}
		$dataReader->close();

		return $recordInstances;
	}

	/**
	 * Function returns all the child comment count.
	 *
	 * @return <type>
	 */
	public function getChildCommentsCount()
	{
		$recordId = $this->getId();
		$queryGenerator = new \App\QueryGenerator('ModComments');
		$queryGenerator->setFields([]);
		$queryGenerator->setSourceRecord($recordId);
		$queryGenerator->addNativeCondition(['parent_comments' => $recordId, 'related_to' => $this->get('related_to')]);

		return $queryGenerator->createQuery()->count();
	}

	/**
	 * Function returns all the comment count.
	 *
	 * @return <int>
	 */
	public static function getCommentsCount($recordId)
	{
		if (empty($recordId)) {
			return 0;
		}
		$queryGenerator = new \App\QueryGenerator('ModComments');
		$queryGenerator->setFields([]);
		$queryGenerator->setSourceRecord($recordId);
		$queryGenerator->addNativeCondition(['related_to' => $recordId]);

		return $queryGenerator->createQuery()->count('modcommentsid');
	}

	/**
	 * Returns child comments models for a comment.
	 *
	 * @return ModComment_Record_Model[]
	 */
	public function getChildComments()
	{
		$parentCommentId = $this->getId();
		if (empty($parentCommentId)) {
			return;
		}
		$queryGenerator = new \App\QueryGenerator('ModComments');
		$queryGenerator->setFields(['parent_comments', 'createdtime', 'modifiedtime', 'related_to', 'id',
			'assigned_user_id', 'commentcontent', 'creator', 'reasontoedit', 'userid', 'parents']);
		//Condition are directly added as query_generator transforms the
		//reference field and searches their entity names
		$queryGenerator->addNativeCondition(['parent_comments' => $parentCommentId, 'related_to' => $this->get('related_to')]);
		$dataReader = $queryGenerator->createQuery()->createCommand()->query();
		$recordInstances = [];
		while ($row = $dataReader->read()) {
			$recordInstance = new self();
			$recordInstance->setData($row)->setModuleFromInstance($queryGenerator->getModuleModel());
			$recordInstances[] = $recordInstance;
		}
		return $recordInstances;
	}

	/**
	 * Returns parent comment models for a comment.
	 *
	 * @return ModComment_Record_Model[]
	 */
	public function getParentComments()
	{
		$commentId = $this->getId();
		$parentCommentId = explode('::', $this->get('parents'))[0];
		if (empty($commentId) || empty($parentCommentId)) {
			return;
		}
		$queryGenerator = new \App\QueryGenerator('ModComments');
		$queryGenerator->setFields(['parent_comments', 'createdtime', 'modifiedtime', 'related_to', 'id',
			'assigned_user_id', 'commentcontent', 'creator', 'reasontoedit', 'userid', 'parents']);
		$queryGenerator->addNativeCondition(['modcommentsid' => $parentCommentId, 'related_to' => $this->get('related_to')]);
		$dataReader = $queryGenerator->createQuery()->createCommand()->query();
		$recordInstances = [];
		while ($row = $dataReader->read()) {
			$recordInstance = new self();
			$recordInstance->setData($row)->setModuleFromInstance($queryGenerator->getModuleModel());
			$recordInstances[] = $recordInstance;
		}
		return $recordInstances;
	}

	/**
	 * Function returns all the parent comments model.
	 *
	 * @param int                 $parentId
	 * @param string              $searchValue
	 * @param bool                $isWidget
	 * @param int[]               $hierarchy
	 * @param Vtiger_Paging_Model $pagingModel
	 *
	 * @return \ModComments_Record_Model[]
	 */
	public static function getSearchComments($parentId, $searchValue, $isWidget, $hierarchy = false, $pagingModel = false)
	{
		$queryGenerator = new \App\QueryGenerator('ModComments');
		$queryGenerator->setFields(['parent_comments', 'createdtime', 'modifiedtime', 'related_to', 'id',
			'assigned_user_id', 'commentcontent', 'creator', 'customer', 'reasontoedit', 'userid', 'parents']);
		$queryGenerator->setSourceRecord($parentId);
		if (empty($hierarchy) || (count($hierarchy) === 1 && reset($hierarchy) === 0)) {
			$queryGenerator->addNativeCondition(['related_to' => $parentId]);
		} else {
			$recordIds = \App\ModuleHierarchy::getRelatedRecords($parentId, $hierarchy);
			if (empty($recordIds)) {
				return [];
			}
			$queryGenerator->addNativeCondition(['related_to' => $recordIds]);
		}
		$queryGenerator->addNativeCondition(['like', 'commentcontent', '%' . $searchValue . '%', false]);
		$query = $queryGenerator->createQuery()->orderBy(['vtiger_crmentity.createdtime' => SORT_DESC]);
		if ($pagingModel && $pagingModel->get('limit') !== 0) {
			$query->limit($pagingModel->getPageLimit())->offset($pagingModel->getStartIndex());
		}
		$dataReader = $query->createCommand()->query();
		$commentsId = [];
		while ($row = $dataReader->read()) {
			$parentId = explode('::', $row['parents'])[0];
			if (!empty($parentId) && !$isWidget) {
				$commentsId[] = $parentId;
			} else {
				$commentsId[] = $row['id'];
			}
		}
		$dataReader->close();
		$recordInstances = [];
		if (!empty($commentsId)) {
			$queryGeneratorParents = new \App\QueryGenerator('ModComments');
			$queryGeneratorParents->setFields(['parent_comments', 'createdtime', 'modifiedtime', 'related_to', 'id',
				'assigned_user_id', 'commentcontent', 'creator', 'customer', 'reasontoedit', 'userid', 'parents']);
			$queryGeneratorParents->addNativeCondition(['in', 'modcommentsid', array_unique($commentsId)], false);
			$parentQuery = $queryGeneratorParents->createQuery();
			if ($pagingModel && $pagingModel->get('limit') !== 0) {
				$parentQuery->limit($pagingModel->getPageLimit())->offset($pagingModel->getStartIndex());
			}
			$dataReaderParents = $parentQuery->createCommand()->query();
			while ($row = $dataReaderParents->read()) {
				$recordInstance = new self();
				$recordInstance->setData($row)->setModuleFromInstance($queryGeneratorParents->getModuleModel());
				$recordInstances[] = $recordInstance;
			}
		}
		return $recordInstances;
	}

	/**
	 * Function to get the list view actions for the comment.
	 *
	 * @return Vtiger_Link_Model[] - Associate array of Vtiger_Link_Model instances
	 */
	public function getCommentLinks()
	{
		$links = [];
		$stateColors = AppConfig::search('LIST_ENTITY_STATE_COLOR');
		if ($this->privilegeToArchive()) {
			$links[] = Vtiger_Link_Model::getInstanceFromValues([
				'linklabel' => 'LBL_ARCHIVE_RECORD',
				'title' => \App\Language::translate('LBL_ARCHIVE_RECORD'),
				'linkurl' => 'javascript:app.showConfirmation({type: "reloadTab"},this)',
				'linkdata' => [
					'url' => 'index.php?module=' . $this->getModuleName() . '&action=State&state=Archived&sourceView=List&record=' . $this->getId(),
					'confirm' => \App\Language::translate('LBL_ARCHIVE_RECORD_DESC'),
				],
				'linkicon' => 'fas fa-archive',
				'linkclass' => 'btn-xs entityStateBtn',
				'style' => empty($stateColors['Archived']) ? '' : "background: {$stateColors['Archived']};",
				'showLabel' => true,
			]);
		}
		if ($this->privilegeToMoveToTrash()) {
			$links[] = Vtiger_Link_Model::getInstanceFromValues([
				'linklabel' => 'LBL_MOVE_TO_TRASH',
				'title' => \App\Language::translate('LBL_MOVE_TO_TRASH'),
				'linkurl' => 'javascript:app.showConfirmation({type: "reloadTab"},this)',
				'linkdata' => [
					'url' => 'index.php?module=' . $this->getModuleName() . '&action=State&state=Trash&sourceView=List&record=' . $this->getId(),
					'confirm' => \App\Language::translate('LBL_MOVE_TO_TRASH_DESC'),
				],
				'linkicon' => 'fas fa-trash-alt',
				'linkclass' => 'btn-xs entityStateBtn',
				'style' => empty($stateColors['Trash']) ? '' : "background: {$stateColors['Trash']};",
				'showLabel' => true,
			]);
		}
		return $links;
	}
}
