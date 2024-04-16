<?php

namespace App\Http\Controllers;

use App\Models\UserModel;
use App\Models\LevelModel;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    //Menampilkan halaman awal user
    public function index()
    {
        $breadcrumb = (object) [
            'title' => 'Daftar User',
            'list' => ['Home', 'User']
        ];

        $page = (object) [
            'title' => 'Daftar user yang terdaftar dalam sistem'
        ];

        $activeMenu = 'user'; //set menu yang sedang aktif

        $level = LevelModel::all(); //ambil data level untuk filter level

        return view('user.index', ['breadcrumb' => $breadcrumb, 'page' => $page, 'level' => $level, 'activeMenu' => $activeMenu]);
    }

    // Ambil data user dalam bentuk json untuk datatables
    public function list(Request $request)
    {
        $users = UserModel::select('user_id', 'username', 'nama', 'level_id')->with('level');

        //Filter data berdasarkan level_id
        if ($request->level_id) {
            $users->where('level_id', $request->level_id);
        }

        return DataTables::of($users)
            ->addIndexColumn() // menambahkan kolom index / no urut (default nama kolom: DT_RowIndex)
            ->addColumn('aksi', function ($user) { // menambahkan kolom aksi
                $btn = '<a href="' . url('/user/' . $user->user_id) . '" class="btn btn-info btn-sm">Detail</a> ';
                $btn .= '<a href="' . url('/user/' . $user->user_id . '/edit') . '" class="btn btn-warning btn-sm">Edit</a> ';
                $btn .= '<form class="d-inline-block" method="POST" action="'
                    . url('/user/' . $user->user_id) . '">' . csrf_field() . method_field('DELETE') .
                    '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Apakah Anda yakin menghapus data ini?\');">Hapus</button></form>';
                return $btn;
            })
            ->rawColumns(['aksi']) // memberitahu bahwa kolom aksi adalah html
            ->make(true);
    }

    //Menampilkan halaman form tambah user
    public function create()
    {
        $breadcrumb = (object) [
            'title' => 'Tambah User',
            'list' => ['Home', 'User', 'Tambah']
        ];

        $page = (object) [
            'title' => 'Tambah User'
        ];

        $level = LevelModel::all(); //ambil data level untuk ditampilkan di form
        $activeMenu = 'user'; //set menu yang sedang aktif
        $level_id = LevelModel::all(); // Mengambil semua isi tabe

        return view('user.create', ['level_id' => $level_id, 'breadcrumb' => $breadcrumb, 'page' => $page, 'level' => $level, 'activeMenu' => $activeMenu]);
    }

    // Menyimpan data user baru
    public function store(Request $request)
    {
        $request->validate([
            //Username harus diisi, berupa string, minimal 3 karakter, dan bernilai unik di tabel m_user kolom username
            'username' => 'required|string|min:3|unique:m_user,username',
            'nama' => 'required|string|max:100', //nama harus diisi, berupa string, maksimal 100 karakter
            'password' => 'required|min:5', //password harus diisi, minimal 5 karakter
            'level_id' => 'required|integer', //level_id harus diisi, berupa angka
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);


        if ($request->hasFile('picture')) {
            $file = $request->file('picture');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            // Save the file directly in the public directory under 'profile_picture'
            $file->move(public_path('profile_picture'), $filename);
        } else {
            $filename = 'default.jpg';
        }

        UserModel::create([
            'username' => $request->username,
            'nama' => $request->nama,
            'password' => bcrypt($request->password), //password dienkripsi sebelum disimpan
            'level_id' => $request->level_id,
            'profile_picture' => $filename,
        ]);

        return redirect('/user')->with('status', 'Data user berhasil ditambahkan!');
    }

    //Menampilkan detail user
    public function show(string $id)
    {
        $user = UserModel::with('level')->find($id); //ambil data user berdasarkan id

        $breadcrumb = (object) [
            'title' => 'Detail User',
            'list' => ['Home', 'User', 'Detail']
        ];

        $page = (object) [
            'title' => 'Detail User'
        ];

        $activeMenu = 'user'; //set menu yang sedang aktif

        return view('user.show', ['breadcrumb' => $breadcrumb, 'page' => $page, 'useri' => $user, 'activeMenu' => $activeMenu]);
    }

    //Menampilkan halaman form edit user
    public function edit(string $id)
    {
        $user = UserModel::find($id); //ambil data user berdasarkan id
        $level = LevelModel::all(); //ambil data level untuk ditampilkan di form

        $breadcrumb = (object) [
            'title' => 'Edit User',
            'list' => ['Home', 'User', 'Edit']
        ];

        $page = (object) [
            'title' => 'Edit User'
        ];

        $activeMenu = 'user'; //set menu yang sedang aktif

        return view('user.edit', ['breadcrumb' => $breadcrumb, 'page' => $page, 'user' => $user, 'level' => $level, 'activeMenu' => $activeMenu]);
    }

    //Menyimpan perubahan data user
    public function update(Request $request, string $id)
    {
        Log::info($request);
        $request->validate([
            //Username harus diisi, berupa string, minimal 3 karakter
            //dan bernilai unik di tabel m_user kolom username kecuali untuk user dengan id yang sedang diedit
            'username' => 'required|string|min:3|unique:m_user,username,' . $id . ',user_id',
            'nama' => 'required|string|max:100', //nama harus diisi, berupa string, maksimal 100 karakter
            'password' => 'nullable|min:5', //password bisa diisi atau tidak, minimal 5 karakter
            'level_id' => 'required|integer' //level_id harus diisi, berupa angka
        ]);

        UserModel::find($id)->update([
            'username' => $request->username,
            'nama' => $request->nama,
            'password' => $request->password ? bcrypt($request->password) : UserModel::find($id)->password, //password dienkripsi jika diisi
            'level_id' => $request->level_id
        ]);

        return redirect('/user')->with('status', 'Data user berhasil diubah!');
    }

    //Menghapus data user
    public function destroy(string $id)
    {
        $check = UserModel::find($id); //ambil data user berdasarkan id
        if (!$check) { //untuk mengecek apakah data user dengan id yang dimaksud ada atau tidak
            return redirect('/user')->with('error', 'Data user tidak ditemukan!');
        }

        try {
            UserModel::destroy($id); //hapus data level

            return redirect('/user')->with('success', 'Data user berhasil dihapus!');
        } catch (\Illuminate\Database\QueryException $e) {
            //Jika terjadi error ketika menghapus data, redirect kembali ke halaman dengan membawa pesan error
            return redirect('/user')->with('error', 'Data user gagal dihapus karena masih terdapat tabel lain yang terkait dengan data ini'); //jika terjadi error
        }
    }

    //Export data user ke pdf
    public function exportPdf()
    {
        $users = UserModel::all(); //ambil semua data user

        // return view('user.pdf', ['users' => $users]); //load view pdf.blade.php dan kirim data user

        $pdf = Pdf::loadView('user.pdf', ['users' => $users]); //load view pdf.blade.php dan kirim data user

        return $pdf->download('data_user.pdf'); //download file pdf
    }

    //Export data user ke excel
    public function exportExcel()
    {
        return Excel::download(new UsersExport, 'data_user.xlsx'); //download file excel
    }
}
