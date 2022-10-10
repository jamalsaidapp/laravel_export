<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Rap2hpoutre\FastExcel\FastExcel;

class StockController extends Controller
{
    const ITEM_PER_PAGE = 15;
    public function index(Request $request)
    {
        $searchParams = $request->all();
        $stockQuery = Stock::query();
        $limit = Arr::get($searchParams, 'limit', static::ITEM_PER_PAGE);
        $channel = Arr::get($searchParams, 'channel', '');
        $keyword = Arr::get($searchParams, 'keyword', '');
        $sortCol = Arr::get($searchParams, 'sortCol', '');
        $sortDir = Arr::get($searchParams, 'sortDir', '');

        if (!empty($channel)) {
            $stockQuery->where('name', 'LIKE', '%' . $channel . '%');
        }

        if (!empty($keyword)) {
            $stockQuery->where('name', 'LIKE', '%' . $keyword . '%');
            $stockQuery->orWhere('channels', 'LIKE', '%' . $keyword . '%');
            $stockQuery->orWhere('id', 'LIKE', '%' . $keyword . '%');
        }
        $stockQuery->orderBy($sortCol, $sortDir);

        return $stockQuery->paginate($limit);
    }

    public function exportALl(Request $request)
    {
        $filename = '../storage/app/public/fast-excel-export.xlsx';
        (new FastExcel($this->usersGenerator($request)))->export($filename);
//        return Storage::disk('public')->download('fast-excel-export.xlsx');
        return response()->download(public_path($filename));
    }

    protected function usersGenerator($request)
    {
        // this method of chunking might be a hassle, but this is more optimal than just using cursor and yielding the result
        $searchParams = $request->all();
        $stockQuery = Stock::query();
        $channel = Arr::get($searchParams, 'channel', '');
        $keyword = Arr::get($searchParams, 'keyword', '');

        if (!empty($channel)) {
            $stockQuery->where('name', 'LIKE', '%' . $channel . '%');
        }

        if (!empty($keyword)) {
            $stockQuery->where('name', 'LIKE', '%' . $keyword . '%');
            $stockQuery->orWhere('channels', 'LIKE', '%' . $keyword . '%');
            $stockQuery->orWhere('id', 'LIKE', '%' . $keyword . '%');
        }

        $chunks_per_loop = 5000; // try changing this number according to the size of your data
        $user_count = (clone $stockQuery)->count();
        $chunks = (int) ceil(($user_count / $chunks_per_loop));

        for ($i = 0; $i < $chunks; $i++) {
            $clonedUser = (clone $stockQuery)->skip($i * $chunks_per_loop)
                ->take($chunks_per_loop)
                ->cursor();

            foreach ($clonedUser as $user) {
                yield $user;
            }
        }

        // Normal, straightforward method, for smaller data this is the simplest way to use generator, but for bigger data, this is quite slow and uses a lot of memory

        // foreach (User::cursor() as $user) {
        //     yield $user;
        // }
    }
}
