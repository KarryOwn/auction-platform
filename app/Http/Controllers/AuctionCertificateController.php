<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuctionCertificateController extends Controller
{
    public function download(Request $request, Auction $auction): BinaryFileResponse
    {
        $isStaffReview = $request->user()?->isStaff() ?? false;

        if (! $isStaffReview && ! in_array($auction->status, [Auction::STATUS_ACTIVE, Auction::STATUS_COMPLETED], true)) {
            abort(403);
        }

        $media = $auction->getFirstMedia('authenticity_cert');

        abort_unless($media, 404, 'No certificate uploaded.');

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
