<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use App\Models\User;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $items = Contract::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('contractor_name', 'like', "%{$request->search}%")
                    ->orWhere('contract_number', 'like', "%{$request->search}%")
                    ->orWhere('supervisor_name', 'like', "%{$request->search}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('contract.index', compact('items'));
    }

    public function create()
    {
        $supervisors = User::role('supervisor')->get();

        return view('contract.create', compact('supervisors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_name' => 'required|string|max:255',
            'contract_number' => 'required|string|max:255|unique:contracts,contract_number',
            'supervisor_id' => 'required|exists:users,id',
        ]);


        $validated['creator_id'] = auth()->id();


        Contract::create($validated);


        return redirect()
            ->route('contract.index')
            ->with('success', 'پیمان با موفقیت ثبت شد.');
    }
    public function show(Contract $contract)
    {
        return view('contract.show', compact('contract'));
    }

    public function edit(Contract $contract)
    {
        $supervisors = User::role('supervisor')->get();

        return view('contract.edit', compact('contract', 'supervisors'));
    }

    public function update(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'contractor_name' => 'required|string|max:255',
            'contract_number' => 'required|string|max:255|unique:contracts,contract_number,' . $contract->id,
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $contract->update($validated);

        return redirect()
            ->route('contract.index')
            ->with('success', 'پیمان با موفقیت ویرایش شد.');
    }

    public function destroy(Contract $contract)
    {
        $contract->delete();

        return redirect()
            ->route('contract.index')
            ->with('success', 'پیمان حذف شد.');
    }
}
