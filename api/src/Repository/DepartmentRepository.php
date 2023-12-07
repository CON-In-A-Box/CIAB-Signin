<?php declare(strict_types=1);

namespace App\Repository;

use Exception;
use Atlas\Query\Select;

class DepartmentRepository implements RepositoryInterface
{

    protected $db;


    public function __construct($db)
    {
        $this->db = $db;

    }


    public function insert(/*.mixed.*/$data): void
    {
        throw new Exception(__CLASS__.": Method '__FUNCTION__' not implemented");

    }


    public function selectAll(): array
    {
        $select = Select::new($this->db);
        $select->columns(
            'depts.DepartmentID',
            'depts.Name',
            'parentDepts.DepartmentID as ParentID',
            'parentDepts.Name as ParentName',
            'fallbackDepts.DepartmentID as FallbackID',
            'fallbackDepts.Name as FallbackName'
        )
            ->columns(
                $select->subSelect()
                    ->columns('COUNT(DepartmentID)')
                    ->from('Departments as childDepts')
                    ->where('childDepts.ParentDepartmentID = depts.DepartmentID')
                    ->andWhere('Name != "Historical Placeholder"')
                    ->as('ChildCount')
                    ->getStatement()
            )
            ->columns(
                $select->subSelect()
                    ->columns('GROUP_CONCAT(Email)')
                    ->from('EMails')
                    ->where('DepartmentID = depts.DepartmentID')
                    ->as('Email')
                    ->getStatement()
            )
            ->from('Departments as depts')
            ->join('LEFT', 'Departments as parentDepts', 'parentDepts.DepartmentID = depts.ParentDepartmentID')
            ->join('LEFT', 'Departments as fallbackDepts', 'fallbackDepts.DepartmentID = depts.FallbackID');

        $historicalPlaceholders = $select->subSelect()->columns('DepartmentID')->from('Departments')->where('Name = "Historical Placeholder"');

        $select->where('depts.DepartmentID NOT IN ', $historicalPlaceholders)
            ->where('depts.ParentDepartmentID NOT IN ', $historicalPlaceholders);

        return $select->fetchAll();

    }


    public function selectById(/*.mixed.*/$departmentId): array
    {
        $select = Select::new($this->db);
        $select->columns(
            'depts.DepartmentID',
            'depts.Name'
        )
            ->from('Departments as depts')
            ->where('depts.Name != "Historical Placeholder"');
        if (is_array($departmentId)) {
            $select->andWhere('(depts.DepartmentID IN ', $departmentId);
        } else {
            $select->andWhere('(depts.DepartmentID = ', $departmentId);
        }
        $select->catWhere(' OR depts.ParentDepartmentID = ', $departmentId)
            ->catWhere(')');

        return $select->fetchAll();

    }


    public function update(/*.string.*/$id, /*.mixed.*/$data): void
    {
        throw new Exception(__CLASS__.":Method '__FUNCTION__' not implemented");

    }


    public function deleteById(/*.mixed.*/$id): void
    {
        throw new Exception(__CLASS__.": Method '__FUNCTION__' not implemented");

    }


    /* End DepartmentRepository */
}
