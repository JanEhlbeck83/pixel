<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCommunityGroupController extends Controller
{
    public function index()
    {
        return view('admin.community-groups', [
            'groups' => CommunityGroup::query()->with('founder')->withCount('members')->latest()->paginate(30),
        ]);
    }

    public function approve(Request $request, CommunityGroup $group)
    {
        DB::transaction(function () use ($request, $group) {
            $group = CommunityGroup::query()->lockForUpdate()->findOrFail($group->id);
            abort_unless($group->status === 'pending', 409);

            $group->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'admin_note' => null,
            ]);
            CommunityGroupMember::query()->firstOrCreate(
                ['user_id' => $group->founder_id],
                ['community_group_id' => $group->id, 'role' => 'founder']
            );
        });

        return back()->with('status', __('Die Gruppe wurde freigegeben.'));
    }

    public function reject(Request $request, CommunityGroup $group)
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        abort_unless($group->status === 'pending', 409);
        $group->update(['status' => 'rejected', 'admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', __('Der Gruppenantrag wurde abgelehnt.'));
    }
}
