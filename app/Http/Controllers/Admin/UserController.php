<?php

namespace App\Http\Controllers\Admin;

use Throwable;
use App\Hasfile;
use App\Models\User;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use App\Enums\UserGender;
use App\Enums\MessageType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\Admin\UserResource;

class UserController extends Controller
{
     use Hasfile;
    public function index(): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'username', 'email', 'phone', 'avatar', 'gender', 'date_of_birth', 'address', 'created_at'])
            ->filter(request()->only(['search']))
            ->sorting(request()->only(['field', 'direction']))
            ->paginate(request()->load ?? 10)
            ->withQueryString();

        return inertia('Admin/Users/Index', [
            'page_settings' => [
                'title' => 'Pengguna',
                'subtitle' => 'Menampilkan semua data pengguna yang tersedia pada platform ini. '
            ],
            'users' => UserResource::collection($users)->additional([
                'meta' => [
                    'has_pages' => $users->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()->page ?? 1,
                'search' => request()->search ?? '',
                'load' => 10,
            ],
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/Users/Create', [
            'page_settings' => [
                'title' => 'Tambah Pengguna',
                'subtitle' => 'Tambah pengguna baru disini. Klik simpan setelah selesai',
                'method' => 'POST',
                'action' => route('admin.users.store'),
            ],
            'genders' => UserGender::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        try {
            $user = User::create([
                'name' => $name = $request->name,
                'username' => usernameGenerator($name),
                'email' => $request->email,
                'password' => Hash::make($request->input('password')),
                'phone' => $request->phone,
                'avatar' => $this->upload_file($request, 'avatar', 'users'),
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
            ]);

            flashMessage(MessageType::CREATED->message('Pengguna'));
            return to_route('admin.users.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.users.index');
        }
    }

    public function edit(User $user): Response
    {
        return inertia('Admin/Users/Edit', [
            'page_settings' => [
                'title' => 'Edit Pengguna',
                'subtitle' => 'Edit pengguna baru disini. Klik simpan setelah selesai',
                'method' => 'PUT',
                'action' => route('admin.users.update', $user),
            ],
            'genders' => UserGender::options(),
            'user' => $user,
        ]);
    }

    public function update(User $user, UserRequest $request): RedirectResponse
    {
        try {
            $attributes = [
                'name' => $name = $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'avatar' => $this->update_file($request, $user,'avatar', 'users'),
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
            ];

            if ($request->filled('password')) {
                $attributes['password'] = Hash::make($request->input('password'));
            }

            $user->update($attributes);

            flashMessage(MessageType::UPDATED->message('Pengguna'));
            return to_route('admin.users.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.users.index');
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        try {
            DB::transaction(function () use ($user): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($lockedUser->id === auth()->id() || Loan::where('user_id', $lockedUser->id)->exists()) {
                    throw new \RuntimeException('Akun sendiri atau pengguna dengan riwayat penyewaan tidak dapat dihapus.');
                }
                $lockedUser->delete();
            }, 3);
            $this->delete_file($user, 'avatar');

            flashMessage(MessageType::DELETED->message('Pengguna'));
            return to_route('admin.users.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.users.index');
        }
    }
}
