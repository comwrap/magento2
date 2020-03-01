<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\AsynchronousOperations\Model\Repository;

use Magento\AsynchronousOperations\Api\BulkSummaryRepositoryInterface;
use Magento\AsynchronousOperations\Api\Data\BulkSummaryInterface;
use Magento\AsynchronousOperations\Model\ResourceModel\Bulk as BulkSummaryResource;
use Magento\AsynchronousOperations\Model\ResourceModel\Operation as OperationResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\AsynchronousOperations\Api\Data\BulkSummaryInterfaceFactory;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\AsynchronousOperations\Model\ResourceModel\Bulk\CollectionFactory as BulksCollectionFactory;
use Magento\AsynchronousOperations\Model\ResourceModel\Operation\CollectionFactory as OperationsCollectionFactory;
use Magento\AsynchronousOperations\Api\Data\BulkSearchResultsInterfaceFactory as BulkSearchResultFactory;
use Magento\AsynchronousOperations\Api\Data\OperationSearchResultsInterfaceFactory as OperationSearchResultFactory;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\Framework\Api\SearchResults;

class BulkSummary implements BulkSummaryRepositoryInterface
{
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var BulksCollectionFactory
     */
    private $bulksCollectionFactory;

    /**
     * @var OperationsCollectionFactory
     */
    private $operationsCollectionFactory;

    /**
     * @var BulkSummaryResource
     */
    private $bulkSummaryResource;

    /**
     * @var BulkSummaryInterfaceFactory
     */
    private $bulkSummaryFactory;

    /**
     * @var OperationInterfaceFactory
     */
    private $operationFactory;

    /**
     * @var OperationResource
     */
    private $operationResource;

    /**
     * @var JoinProcessorInterface
     */
    private $joinProcessor;

    /**
     * @var BulkSearchResultFactory
     */
    private $bulkSearchResultFactory;

    /**
     * @var OperationSearchResultFactory
     */
    private $operationSearchResultFactory;

    /**
     * BulkSummary constructor.
     *
     * @param BulkSummaryResource $bulkSummaryResource
     * @param BulkSummaryInterfaceFactory $bulkSummaryFactory
     * @param OperationResource $operationResource
     * @param BulksCollectionFactory $bulksCollectionFactory
     * @param OperationsCollectionFactory $operationsCollectionFactory
     * @param JoinProcessorInterface $joinProcessor
     * @param CollectionProcessorInterface $collectionProcessor
     * @param BulkSearchResultFactory $bulkSearchResultFactory
     * @param OperationSearchResultFactory $operationSearchResultFactory
     * @param OperationInterfaceFactory $operationFactory
     */
    public function __construct(
        BulkSummaryResource $bulkSummaryResource,
        BulkSummaryInterfaceFactory $bulkSummaryFactory,
        OperationResource $operationResource,
        BulksCollectionFactory $bulksCollectionFactory,
        OperationsCollectionFactory $operationsCollectionFactory,
        JoinProcessorInterface $joinProcessor,
        CollectionProcessorInterface $collectionProcessor,
        BulkSearchResultFactory $bulkSearchResultFactory,
        OperationSearchResultFactory $operationSearchResultFactory,
        OperationInterfaceFactory $operationFactory
    ) {
        $this->bulkSummaryResource = $bulkSummaryResource;
        $this->bulkSummaryFactory = $bulkSummaryFactory;
        $this->operationResource = $operationResource;
        $this->bulksCollectionFactory = $bulksCollectionFactory;
        $this->operationsCollectionFactory = $operationsCollectionFactory;
        $this->joinProcessor = $joinProcessor;
        $this->collectionProcessor = $collectionProcessor;
        $this->bulkSearchResultFactory = $bulkSearchResultFactory;
        $this->operationSearchResultFactory = $operationSearchResultFactory;
        $this->operationFactory = $operationFactory;
    }

    /**
     * @inheritDoc
     */
    public function saveBulk(BulkSummaryInterface $entity): void
    {
        try {
            $this->bulkSummaryResource->save($entity);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function saveOperations(array $operations): void
    {
        try {
            $connection = $this->operationResource->getConnection();
            $connection->insertMultiple(
                $this->operationResource->getTable(OperationResource::TABLE_NAME),
                $operations
            );
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getOperationsList(SearchCriteriaInterface $searchCriteria): SearchResults
    {
        $searchResult = $this->operationSearchResultFactory->create();
        $collection = $this->operationsCollectionFactory->create();
        $this->joinProcessor->process($collection, OperationInterface::class);
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setTotalCount($collection->getSize());
        $searchResult->setItems($collection->getItems());
        return $searchResult;
    }

    public function deleteOperationsById(array $operationIds): void
    {
        $connection = $this->operationResource->getConnection();
        $connection->delete(
            $this->operationResource->getTable(OperationResource::TABLE_NAME),
            $connection->quoteInto('id IN (?)', $operationIds)
        );
    }

    /**
     * @inheritDoc
     */
    public function getBulkByUuid(string $bulkUuid): BulkSummaryInterface
    {
        $bulkSummary = $this->bulkSummaryFactory->create();
        $this->bulkSummaryResource->load($bulkSummary, $bulkUuid, BulkSummaryResource::TABLE_PRIMARY_KEY);
        if (!$bulkSummary->getId()) {
            throw new NoSuchEntityException(__('The Bulk summary with the "%1" UUID doesn\'t exist.', $bulkUuid));
        }
        return $bulkSummary;
    }

    /**
     * @inheritDoc
     */
    public function getBulksList(SearchCriteriaInterface $searchCriteria): SearchResults
    {
        $searchResult = $this->bulkSearchResultFactory->create();
        $collection = $this->bulksCollectionFactory->create();
        $this->joinProcessor->process($collection, BulkSummaryInterface::class);
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setTotalCount($collection->getSize());
        $searchResult->setItems($collection->getItems());
        return $searchResult;
    }
}
