<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use Inertia\Inertia;

class CustomerController extends Controller
{
    //
    public function index(Request $request){
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'supervisor', 'cs']),
            403
        );

        $customers = Customer::with('reseller')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'      => $c->id,
                'name'    => $c->name,
                'phone'   => $c->phone,
                'email'   => $c->email,
                'address' => $c->address,
                'reseller'=> $c->reseller?->name,
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
        ]);
    }
}
