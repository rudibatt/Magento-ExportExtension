<?php
/**
 * this class adds small functions to modify the export data
 *
 * @copyright  Copyright (c) 2009 Omikron Data Quality GmbH (http://www.omikron.net)
 * @author     Rudolf Batt (rb@omikron.net)
 */


class Omikron_ExportExtension_Model_Modifier extends Mage_Dataflow_Model_Convert_Parser_Abstract
{	
	private $_categoryPathCache;
	private $_categoryFieldName;
	private $_categoryDelimiter;
	private $_categoryPathDelimiter;
	private $_firstCategoryLevel;
	
	protected function _removeHtmlTags(&$row)
	{
		foreach ($row AS $key => $value) {
			$row[$key] = preg_replace('#</?.*?>#', ' ', $value);
		}
	}
	
	/**
	 * cleans content from setted strings
	 */
	protected function _removeLineBreaks(&$row)
	{
		foreach ($row AS $key => $value) {
			$row[$key] = str_replace(array("\r", "\n"), '', $value);
		}
	}
	
	/**
	 * adds categories to the row
	 */
	protected function _addCategories(&$row, &$product)
	{
		$categoryFieldName = $this->_getCategoryFieldName();
		$categoryDelimiter = $this->_getCategoryDelimiter();
		
		$row[$categoryFieldName] = '';
        $tempCatPath = '';
		foreach($product->getCategoryIds() as $categoryId){
            //dont add delimiter if previous category path was empty or no category was added yet
			if( $tempCatPath != '' && $row[$categoryFieldName] != '') {
				$row[$categoryFieldName] .= $categoryDelimiter;
			}
			
            $tempCatPath = $this->_getCategoryPath($categoryId);
			if ($tempCatPath != '') {
                $row[$categoryFieldName] .= $tempCatPath;
            }
		}
	}
	
	/**
	 * modifies each data
	 */
	public function unparse()
	{
		$addCategories         = $this->getVar('add_categories', '') == 'true' ? true : false;
		$removeLineBreaks      = $this->getVar('remove_line_breaks', '') == 'true' ? true : false;
		$removeHtmlTags        = $this->getVar('remove_html_tags', '') == 'true' ? true : false;
		
		$addAbsoluteUrlToField = $this->getVar('add_absolute_url_to_field', '');
		if (empty($addAbsoluteUrlToField)) {
			$addAbsoluteUrlToField = false;
		}
		
		$addImageUrlToField = $this->getVar('add_image_url_to_field', '');
		if (empty($addImageUrlToField)) {
			$addImageUrlToField = false;
		}
		
		if (!$addCategories && !$removeLineBreaks && !$removeHtmlTags) {
			$this->addException("no modifier activated!", Varien_Convert_Exception::NOTICE);
			return $this;
		}
		
		//init
        $batchExport    = $this->getBatchExportModel();
        $batchExportIds = $batchExport
			->setBatchId($this->getBatchModel()->getId())
			->getIdCollection();
		
		$productIds     = $this->getData();
		$product        = Mage::getModel('catalog/product');
		$productHelper  = Mage::helper('catalog/product');
		$productCounter = 0;
		
		//start modifying data
		foreach ($batchExportIds as $batchExportId) {
            $batchExport->load($batchExportId);
            $row = $batchExport->getBatchData();
			$product->reset();
			$product->load($productIds[$productCounter]);
			
			if ($addCategories) {
				$this->_addCategories($row, $product);
			}
			if ($removeLineBreaks) {
				$this->_removeLineBreaks($row);
			}
			if ($removeHtmlTags) {
				$this->_removeHtmlTags($row);
			}
			if ($addAbsoluteUrlToField !== false) {
				try{
					$_baseurl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
					$row[$addAbsoluteUrlToField] = $_baseurl.$product->getUrlPath();
				} catch (Exception $e) { /* and forget */}
			}
			if ($addImageUrlToField !== false) {
				try{
					$row[$addImageUrlToField] = $productHelper->getImageUrl($product);
				} catch (Exception $e) { /* and forget */}
			}
			
            $batchExport->setBatchData($row)
				->setStatus(2)
				->save();
			
			if ($addCategories) {
				$this->getBatchModel()->parseFieldList($batchExport->getBatchData());
			}
			
			$productCounter++;
        }
		
		return $this;
	}
	
	public function parse()
	{
		$this->addException("category parser not implemented, only use 'unparse' to modify export data", Varien_Convert_Exception::WARNING);
		return $this;
	}
	
	/**
	 * returns the category fieldname for the export
	 */
	private function _getCategoryFieldName()
	{
		if ($this->_categoryFieldName == null) {
			$this->_categoryFieldName = $this->getVar('category_field_name', 'categories');
		}
		return $this->_categoryFieldName;
	}
	
	/**
	 * returns the category delimiter used at the export
	 */
	private function _getCategoryDelimiter()
	{
		if ($this->_categoryDelimiter == null) {
			$this->_categoryDelimiter = $this->getVar('category_delimiter', '#');
		}
		return $this->_categoryDelimiter;
	}
	
	/**
	 * returns the category path delimiter used at the export
	 */
	private function _getCategoryPathDelimiter()
	{
		if (!$this->_categoryPathDelimiter) {
			$this->_categoryPathDelimiter = $this->getVar('category_path_delimiter', '>');
		}
		return $this->_categoryPathDelimiter;
	}
	
	/**
	 * returns the number of the category level, which should be exported first at the category path
	 */
	private function _getFirstCategoryLevel()
	{
		if (!$this->_firstCategoryLevel) {
			$this->_firstCategoryLevel = intval($this->getVar('first_category_level', 1));
		}
		return $this->_firstCategoryLevel;
	}
	
	/**
	 * returns the category path with names
	 */
	private function _getCategoryPath($categoryId)
	{
		$categoryId = (string)$categoryId;
		
		if (!isset($this->_categoryPathCache[$categoryId])) {
			$cpd = $this->_getCategoryPathDelimiter();
			$firstLevel = $this->_getFirstCategoryLevel();
			
			$categoryPath = '';
			$category = Mage::getModel('catalog/category')->load($categoryId);
			
			if ($category->getIsActive() != 1) {
                //if is not active, do nothing => add empty string as "path"
            } else if ($firstLevel == -1) {
				//if first_category_level is -1, only export the category name
				$categoryPath = $category->getName();
			} else {
				//if first_category_level is not -1, export path, starting from 'path_category_level'
				while ($category->getParentId() != 0 && $category->getLevel() >= $firstLevel) {
					if ($categoryPath != '') {
						$categoryPath = $cpd . $categoryPath;
					}
					$categoryPath = $category->getName() . $categoryPath;
					$category = $category->getParentCategory();
				}
			}
			
			$this->_categoryPathCache[$categoryId] = $categoryPath;
		}
		
		return $this->_categoryPathCache[$categoryId];
	}
}
