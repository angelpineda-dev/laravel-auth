<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Image;
use App\Models\Room;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use CloudinaryLabs\CloudinaryLaravel\MediaAlly;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Validator;

class RoomController extends Controller
{

  use MediaAlly;

  public function store(Request $request)
  {
    $validator = Validator::make(data: $request->all(), rules: [
      'name' => 'string|required',
      'price' => 'required',
      'images' => 'nullable|array',
      'images.*' => 'file|image|max:2048'
    ]);

    if ($validator->fails()) {
      return ApiResponse::error(message: 'Validation error', errors: $validator->errors());
    }

    $page = $request->input(key: 'page', default: 1);
    $per_page = $request->input(key: 'per_page', default: 10);

    try {

      $attributes = $request->except(keys: ['services', 'images', 'per_page', 'page']);

      $room = Room::create(attributes: $attributes);

      if ($room) {
        $services = $request->input(key: 'services');
        $room->services()->sync($services);

        $files = $request->file(key: 'images');

        if (isset($files)) {

          foreach ($files as $file) {
            $cloudinary_result = Cloudinary::upload(file: $file->getRealPath());

            if ($cloudinary_result) {
              $image = new Image();
              $image->url = $cloudinary_result->getSecurePath();
              $image->public_id = $cloudinary_result->getPublicId();
              $image->save();

              $room->images()->attach($image->id);
            }
          }
        }
      }

      $query = Room::query()->with(relations: ['floor', 'size', 'services', 'images']);

      $data = $this->paginateData(query: $query, perPage: $per_page, page: $page);

      return ApiResponse::success(data: $data, message: "", code: Response::HTTP_CREATED);

    } catch (\Exception $e) {

      return ApiResponse::error(message: 'Not expected error ', errors: $e->getMessage(), code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  public function index(Request $request)
  {
    $page = $request->input(key: 'page', default: 1);
    $search = $request->input(key: 'search', default: '');
    $column = $request->input(key: 'column', default: 'name');
    $per_page = $request->input(key: 'per_page', default: 10);

    try {

      $query = Room::query()->with(relations: ['floor', 'size', 'services', 'images']);

      if ($search) {
        $query->where(column: $column, operator: 'like', value: '%' . $search . '%');
      }

      $data = $this->paginateData(query: $query, perPage: $per_page, page: $page);

      return ApiResponse::success(data: $data);

    } catch (\Exception $e) {
      return ApiResponse::error(message: 'Not expected error ', errors: $e->getMessage(), code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  public function show(string $id)
  {

    try {
      $data = Room::where(column: 'id', operator: $id)->with(relations: ['floor', 'size', 'services'])->get();

      return ApiResponse::success(data: $data);

    } catch (ModelNotFoundException $e) {

      return ApiResponse::error(message: 'Resource not found ', errors: $e->getMessage(), code: Response::HTTP_NOT_FOUND);
    } catch (\Exception $e) {

      return ApiResponse::error(message: 'Not expected error ', errors: $e->getMessage(), code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  public function update(Request $request, string $id)
  {

    $validator = Validator::make(data: $request->all(), rules: [
      'name' => 'string|required',
      'price' => 'required'
    ]);

    if ($validator->fails()) {
      return ApiResponse::error(message: 'Validation error', errors: $validator->errors());
    }

    $page = $request->input(key: 'page', default: 1);
    $per_page = $request->input(key: 'per_page', default: 10);

    try {
      $room = Room::findOrFail(id: $id);

      $attributes = $request->except(keys: 'services[]');
      $services = $request->input(key: 'services[]');

      if ($room) {
        $room->update(attributes: $attributes);
        $room->services()->sync($services);
      }

      $query = Room::query()->with(relations: ['floor', 'size', 'services']);

      $data = $this->paginateData(query: $query, perPage: $per_page, page: $page);

      return ApiResponse::success(data: $data);

    } catch (ModelNotFoundException $e) {

      return ApiResponse::error(message: 'Resource not found ', errors: $e->getMessage(), code: Response::HTTP_NOT_FOUND);
    } catch (\Exception $e) {

      return ApiResponse::error(message: 'Not expected error ', errors: $e->getMessage(), code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  public function delete(Request $request, string $id)
  {
    $page = $request->input(key: 'page', default: 1);
    $per_page = $request->input(key: 'per_page', default: 10);
    try {
      $room = Room::findOrFail(id: $id);

      if ($room) {

        $this->deleteImagesFromCloudinary(images: $room->images);

        $room->delete();

        $query = Room::query()->with(relations: ['floor', 'size', 'services']);

        $data = $this->paginateData(query: $query, perPage: $per_page, page: $page);

        return ApiResponse::success($data);
      }

    } catch (ModelNotFoundException $e) {

      return ApiResponse::error(message: 'Resource not found ', errors: $e->getMessage(), code: Response::HTTP_NOT_FOUND);
    } catch (\Exception $e) {

      return ApiResponse::error(message: 'Not expected error ', errors: $e->getMessage(), code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}
