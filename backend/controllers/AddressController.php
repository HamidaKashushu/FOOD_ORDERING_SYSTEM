<?php
/**
 * Food Ordering System - AddressController
 * Handles address management for authenticated users.
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class AddressController
{
    private Address $addressModel;
    private Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->addressModel = new Address();
    }

    /**
     * Get all addresses for authenticated user (GET /addresses)
     */
    public function getUserAddresses(): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $addresses = $this->addressModel->getByUserId($userId);
        Response::success($addresses, 'Addresses retrieved successfully');
    }

    /**
     * Create new address (POST /addresses)
     */
    public function createAddress(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'street'  => 'required|string|min:3',
            'city'    => 'required|string',
            'country' => 'required|string',
            'phone'   => 'optional|string' // Validated at user level, but useful if per-address phone needed
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Address creation failed');
        }

        $addressData = [
            'user_id' => $userId,
            'street'  => $data['street'],
            'city'    => $data['city'],
            'region'  => $data['region'] ?? '',
            'zip'     => $data['zip'] ?? '',
            'country' => $data['country'],
            'type'    => $data['type'] ?? 'home',
            'status'  => 'active'
        ];

        if ($this->addressModel->create($addressData)) {
            Response::created(['message' => 'Address added successfully']);
        }

        Response::error('Failed to add address', 500);
    }
}
