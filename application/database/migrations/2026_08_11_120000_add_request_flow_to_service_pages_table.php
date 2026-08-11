<?php

use App\Modules\Content\Enums\ServiceRequestFlow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_pages', function (Blueprint $table): void {
            $table->string('request_flow', 32)
                ->default(ServiceRequestFlow::Generic->value)
                ->after('publication_status')
                ->index();
        });

        DB::table('service_pages')
            ->whereIn('slug', ['mobilt-dekkskift', 'dekkverksted'])
            ->update(['request_flow' => ServiceRequestFlow::Seasonal->value]);
    }

    public function down(): void
    {
        Schema::table('service_pages', function (Blueprint $table): void {
            $table->dropIndex(['request_flow']);
            $table->dropColumn('request_flow');
        });
    }
};
