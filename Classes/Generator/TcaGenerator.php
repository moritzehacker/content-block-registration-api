<?php

declare(strict_types=1);

/*
 * This file is part of the package typo3-contentblocks/contentblocks-reg-api.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Typo3Contentblocks\ContentblocksRegApi\Generator;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Typo3Contentblocks\ContentblocksRegApi\Backend\Preview\PreviewRenderer;
use Typo3Contentblocks\ContentblocksRegApi\Constants;
use Typo3Contentblocks\ContentblocksRegApi\Service\ConfigurationService;
use Typo3Contentblocks\ContentblocksRegApi\Service\DataService;
use Typo3Contentblocks\ContentblocksRegApi\Service\TcaFieldService;

class TcaGenerator
{
    /**
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @var TcaFieldService
     */
    protected $tcaFieldService;

    /**
     * @var DataService
     */
    protected $dataService;

    public function __construct(
        ConfigurationService $configurationService,
        TcaFieldService $tcaFieldService,
        DataService $dataService
    ) {
        $this->configurationService = $configurationService;
        $this->tcaFieldService = $tcaFieldService;
        $this->dataService = $dataService;
    }

    /**
     * Create the TCA config for all Content Blocks
     **/
    public function setTca() :void
    {
        $configuration = $this->configurationService->configuration();

        foreach ($configuration as $contentBlock) {
            /***************
             * Add Content Element
             */
            if (
                !isset($GLOBALS['TCA']['tt_content']['types'][$contentBlock['CType']]) ||
                !is_array($GLOBALS['TCA']['tt_content']['types'][$contentBlock['CType']])
            ) {
                $GLOBALS['TCA']['tt_content']['types'][$contentBlock['CType']] = [];
            }

            // PreviewRenderer
            $GLOBALS['TCA']['tt_content']['types'][$contentBlock['CType']]['previewRenderer'] =
                PreviewRenderer::class;

            /***************
             * Assign Icon
             */
            $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$contentBlock['CType']] = $contentBlock['CType'];

            /***************
             * Add content element to selector list
             */
            ExtensionManagementUtility::addTcaSelectItem(
                'tt_content',
                'CType',
                [
                    'LLL:' . $contentBlock['EditorInterfaceXlf'] . ':' . $contentBlock['vendor']
                    . '.' . $contentBlock['package'] . '.title',
                    $contentBlock['CType'],
                    $contentBlock['CType'],
                ],
                'header',
                'after'
            );

            /***************
             * Add columns to table TCA of tt_content and tx_contentblocks_reg_api_collection
             */
            $ttContentShowitemFields = '';
            $ttContentColumns = [];
            $collectionColumns = [];
            if (is_array($contentBlock['fields'])
                && count($contentBlock['fields']) > 0
            ) {
                $fieldsList = $contentBlock['fields'];
                foreach ($fieldsList as $field) {
                    $tempUniqueColumnName = $this->dataService->uniqueColumnName($contentBlock['key'], $field['_identifier']);

                    // Add fields to tt_content (first level)
                    if (isset($field['_identifier']) && isset($field['type']) && count($field['_path']) == 1) {
                        $ttContentShowitemFields .= "\n" . $tempUniqueColumnName . ',';
                        $ttContentColumns[$tempUniqueColumnName] = $this->tcaFieldService->getMatchedTcaConfig($contentBlock, $field);
                    // TODO: else throw usefull exeption if not supported
                    }

                    // Add collection fields
                    elseif (isset($field['_identifier']) && isset($field['type']) && count($field['_path']) > 1) {
                        $collectionColumns[$tempUniqueColumnName] = $this->tcaFieldService->getMatchedTcaConfig($contentBlock, $field);
                        // TODO: else throw usefull exeption if not supported
                    }
                }
            }
            $GLOBALS['TCA']['tt_content']['columns'] = array_replace_recursive(
                $GLOBALS['TCA']['tt_content']['columns'],
                $ttContentColumns
            );
            $GLOBALS['TCA'][Constants::COLLECTION_FOREIGN_TABLE]['columns'] = array_replace_recursive(
                $GLOBALS['TCA'][Constants::COLLECTION_FOREIGN_TABLE]['columns'],
                $collectionColumns
            );

            /***************
             * Configure element type
             */
            // Feature: enable pallette frame via extConf
            $enableLayoutOptions = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)
                            ->get('contentblocks_reg_api', 'enableLayoutOptions');
            $GLOBALS['TCA']['tt_content']['types'][$contentBlock['CType']] = array_replace_recursive(
                $GLOBALS['TCA']['tt_content']['types'][$contentBlock['CType']],
                [
                    'showitem' => '
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                            --palette--;;general,
                            header,' . $ttContentShowitemFields . '
                            content_block,
                        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,' . (($enableLayoutOptions) ? '
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.frames;frames,' : '') . '
                            --palette--;;appearanceLinks,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                            --palette--;;language,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                            --palette--;;hidden,
                            --palette--;;access,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
                            categories,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
                            rowDescription,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
                    ',
                ]
            );
        }
    }
}
