<?php declare(strict_types=1);

/**
 * @OA\Tag(
 *     name="emails",
 *     description="Features around emails for departments"
 * )
 *
 * @OA\Schema(
 *     schema="email",
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"email"}
 *     ),
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         description="Email ID"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         description="Email address"
 *     ),
 *     @OA\Property(
 *         property="departmentId",
 *         type="string",
 *         description="The department ID associated with this email"
 *     ),
 *     @OA\Property(
 *         property="isAlias",
 *         type="integer",
 *         description="Flag indicating whether this email is an alias"
 *     )
 * )
 *
 * @OA\Post(
 *     tags={"emails"},
 *     path="/email",
 *     summary="Create new email for a department",
 *     @OA\RequestBody(
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="Email",
 *                     description="The email address for the department",
 *                     nullable=false,
 *                     type="string"
 *                 ),
 *                 @OA\Property(
 *                     property="DepartmentID",
 *                     description="The ID for the department",
 *                     nullable=false,
 *                     type="integer"
 *                 ),
 *                 @OA\Property(
 *                     property="IsAlias",
 *                     description="Indicates whether this email address is an alias",
 *                     nullable=true,
 *                     type="integer"
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="OK",
 *         @OA\JsonContent(
 *             ref="#/components/schemas/email"
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         ref="#/components/responses/401"
 *     ),
 *     @OA\Response(
 *         response=400,
 *         ref="#/components/responses/400"
 *     ),
 *     security={{"ciab_auth":{}}}
 * )
 */
namespace App\Controller\Email;

use App\Controller\BaseController;
use App\Error\InvalidParameterException;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class PostEmail extends BaseController
{

    protected $emailService;

  
    public function __construct(Container $container)
    {
        parent::__construct('email', $container);
        $this->emailService = $container->get("EmailService");

    }


    public static function install($container): void
    {

    }


    public static function permissions($database): ?array
    {
        return null;

    }


    public function buildResource(Request $request, Response $response, $args): array
    {
        $required = ['Email', 'DepartmentID'];
        $body = $this->checkRequiredBody($request, $required);

        if (strlen(trim($body["Email"])) == 0) {
            throw new InvalidParameterException("Email must be a non-empty string");
        }

        if ($body["DepartmentID"] <= 0) {
            throw new InvalidParameterException("DepartmentID must be greater than 0");
        }

        $createdEmail = $this->emailService->post($body);
        $result = $this->emailService->getById($createdEmail);

        return [
          \App\Controller\BaseController::RESOURCE_TYPE,
          $result,
          201
        ];

    }


  /* End PostEmail */
}
