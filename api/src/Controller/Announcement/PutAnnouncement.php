<?php declare(strict_types=1);
/*.
    require_module 'standard';
.*/

namespace App\Controller\Announcement;

require_once __DIR__.'/../../../../backends/email.inc';

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views;

require_once __DIR__.'/../../../../functions/users.inc';

class PutAnnouncement extends BaseAnnouncement
{


    private function sendEmail($department, $scope, $text)
    {
        global $CONSITENAME, $BASEURL;

        $condition = '';

        if ($scope == 1) {
            $condition = <<<SQL
                AND (
                    SELECT
                        COUNT(AccountID)
                    FROM
                        `ConComList`
                    WHERE
                        `AccountID`  = d.AccountID
                ) > 0
SQL;
        } elseif ($scope == 2) {
            $condition = <<<SQL
              AND (
                {$department['id']} IN(
                SELECT
                    `DepartmentID`
                FROM
                    `ConComList`
                WHERE
                    `AccountID` = d.AccountID
            ) OR {$department['id']} IN(
                SELECT
                    `DepartmentID`
                FROM
                    `Departments`
                WHERE
                    `ParentDepartmentID` IN(
                    SELECT
                        `DepartmentID`
                    FROM
                        `ConComList`
                    WHERE
                        `AccountID` = d.AccountID
                )
            ))
SQL;
        }
        $sql = <<<SQL
            SELECT
               Email, firstName
            FROM
                (
                SELECT
                    m.*,
                    CASE WHEN ac.`Value` IS NOT NULL THEN ac.`Value`
                         ELSE af.`InitialValue`
            END AS `Value`
            FROM
                `Members` AS m
            LEFT JOIN `AccountConfiguration` AS ac
            ON
                m.AccountID = ac.AccountID AND ac.FIELD = 'AnnounceEmail'
                AND ac.Value IS NOT NULL
            LEFT JOIN `ConfigurationField` AS af
            ON
                af.Field = 'AnnounceEmail'
            ) AS d
            WHERE
                `Value` = 1
                $condition
SQL;
        $sth = $this->container->db->prepare($sql);
        $sth->execute();
        $members = $sth->fetchAll();

        $subject = "$CONSITENAME New Announcement";

        foreach ($members as $target) {
            $phpView = new Views\PhpRenderer(__DIR__.'/../../Templates', [
                'site' => $BASEURL,
                'department' => $department['Name'],
                'announcement' => $text,
                'con' => $CONSITENAME,
                'url' => $BASEURL.'?Function=configuration',
            ]);
            $response = new Response();
            $phpView->render($response, 'newAnnouncements.phtml');
            $response->getBody()->rewind();
            $message = $response->getBody()->getContents();

            \ciab\Email::mail($target['Email'], \getNoReplyAddress(), $subject, $message);
        }

    }


    public function buildResource(Request $request, Response $response, $args): array
    {
        $department = $this->getDepartment($args['dept']);
        if ($department === null) {
            return [
            \App\Controller\BaseController::RESULT_TYPE,
            $this->errorResponse(
                $request,
                $response,
                'Not Found',
                'Department \''.$args['dept'].'\' Not Found',
                404
            )];
        }
        if (\ciab\RBAC::havePermission('api.put.announcement.'.$department['id']) ||
            \ciab\RBAC::havePermission('api.put.announcement.all')) {
            $body = $request->getParsedBody();
            if (!array_key_exists('Scope', $body)) {
                return [
                \App\Controller\BaseController::RESULT_TYPE,
                $this->errorResponse($request, $response, 'Required \'Scope\' parameter not present', 'Missing Parameter', 400)];
            }
            if (!array_key_exists('Text', $body)) {
                return [
                \App\Controller\BaseController::RESULT_TYPE,
                $this->errorResponse($request, $response, 'Required \'Text\' parameter not present', 'Missing Parameter', 400)];
            }

            $user = $this->findMember($request, $response, null, null);
            $member = $user['id'];
            $text = \MyPDO::quote($body['Text']);

            $sth = $this->container->db->prepare("INSERT INTO `Announcements` (DepartmentID, PostedBy, PostedOn, Scope, Text) VALUES ({$department['id']}, $member, now(), '{$body['Scope']}', $text)");
            $sth->execute();

            if (!array_key_exists('Email', $body) || boolval($body['Email'])) {
                $this->sendEmail($department, intval($body['Scope']), $text);
            }
            return [null];
        } else {
            return [
            \App\Controller\BaseController::RESULT_TYPE,
            $this->errorResponse($request, $response, 'Permission Denied', 'Permission Denied', 403)];
        }

    }


    /* end PutAnnouncement */
}
