<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 12/7/15
 * Time: 12:17 AM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Laravel\JsonApi\Controller;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use NilPortugues\Api\JsonApi\Http\Factory\RequestFactory;
use NilPortugues\Api\JsonApi\Http\Response\ResourceNotFound;
use NilPortugues\Laravel\JsonApi\Actions\CreateResource;
use NilPortugues\Laravel\JsonApi\Actions\DeleteResource;
use NilPortugues\Laravel\JsonApi\Actions\GetResource;
use NilPortugues\Laravel\JsonApi\Actions\ListResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class JsonApiController.
 */
abstract class JsonApiController extends Controller
{
    use JsonApiTrait;

    /**
     * Get many resources.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        $apiRequest = RequestFactory::create();

        $page = $apiRequest->getPage();
        if (!$page->size()) {
            $page->setSize($this->pageSize);
        }

        $fields = $apiRequest->getFields();
        $sorting = $apiRequest->getSort();
        $included = $apiRequest->getIncludedRelationships();
        $filters = $apiRequest->getFilters();

        $resource = new ListResource($this->serializer, $page, $fields, $sorting, $included, $filters);

        $totalAmount = $this->totalAmountResourceCallable();
        $results = $this->listResourceCallable();

        $controllerAction = '\\'.get_called_class().'@index';
        $uri = $this->uriGenerator($controllerAction);

        return $this->addHeaders($resource->get($totalAmount, $results, $uri, get_class($this->getDataModel())));
    }

    /**
     * Get single resource.
     *
     * @param $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function show($id)
    {
        $apiRequest = RequestFactory::create();

        $resource = new GetResource(
            $this->serializer,
            $apiRequest->getFields(),
            $apiRequest->getIncludedRelationships()
        );

        $find = $this->findResourceCallable($id);

        return $this->addHeaders($resource->get($id, get_class($this->getDataModel()), $find));
    }

    /**
     * @return ResourceNotFound
     */
    public function create()
    {
        return new ResourceNotFound();
    }

    /**
     * Post Action.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function store(Request $request)
    {
        $createResource = $this->createResourceCallable();
        $resource = new CreateResource($this->serializer);

        $model = $this->getDataModel();
        $data = (array) $request->get('data');
        if (array_key_exists('attributes', $data) && $model->timestamps) {
            $data['attributes'][$model::CREATED_AT] = Carbon::now()->toDateTimeString();
            $data['attributes'][$model::UPDATED_AT] = Carbon::now()->toDateTimeString();
        }

        return $this->addHeaders(
          $resource->get($data, get_class($this->getDataModel()), $createResource)
        );
    }

    /**
     * @param $id
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        return (strtoupper($request->getMethod()) === 'PUT') ? $this->putAction($request,
            $id) : $this->patchAction($request, $id);
    }

    /**
     * @return ResourceNotFound
     */
    public function edit()
    {
        return new ResourceNotFound();
    }

    /**
     * @param $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $find = $this->findResourceCallable($id);

        $delete = $this->deleteResourceCallable($id);

        $resource = new DeleteResource($this->serializer);

        return $this->addHeaders($resource->get($id, get_class($this->getDataModel()), $find, $delete));
    }
}
