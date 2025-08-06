<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\User;
use Illuminate\Console\Command;

class PopulatePublicationFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'populate:publication-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate missing publication fields for web dashboard compatibility';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 POPULATING PUBLICATION MISSING FIELDS');
        $this->info('=======================================');

        try {
            $publications = Publication::all();
            $this->info('📊 Found '.$publications->count().' publications to update');

            // Get some admin users to assign as creators
            $admins = User::where('role', 'ADMIN')->get();
            if ($admins->isEmpty()) {
                $this->warn('⚠️ No admin users found, using any available users');
                $admins = User::limit(10)->get();
            }

            if ($admins->isEmpty()) {
                $this->error('❌ No users found in database');

                return 1;
            }

            $updated_count = 0;
            $bar = $this->output->createProgressBar($publications->count());
            $bar->start();

            foreach ($publications as $publication) {
                $updates = [];

                // Assign created_by if missing
                if (empty($publication->created_by)) {
                    if ($publication->author_id) {
                        $updates['created_by'] = $publication->author_id;
                    } else {
                        // Assign a random admin as creator
                        $admin = $admins->random();
                        $updates['created_by'] = $admin->id;
                    }
                }

                // Set creator_name if missing
                if (empty($publication->creator_name)) {
                    $creator_id = $updates['created_by'] ?? $publication->created_by;
                    if ($creator_id) {
                        $creator = User::find($creator_id);
                        if ($creator) {
                            $updates['creator_name'] = $creator->name;
                        } else {
                            $updates['creator_name'] = 'Admin Astacala';
                        }
                    } else {
                        $updates['creator_name'] = 'Admin Astacala';
                    }
                }

                if (! empty($updates)) {
                    $publication->update($updates);
                    $updated_count++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("🎉 SUCCESS! Updated $updated_count publications");
            $this->info('📊 All publications now have:');
            $this->info('   ✅ created_by (user ID who created publication)');
            $this->info("   ✅ creator_name (for 'Dibuat Oleh' column)");

            // Test query to verify the data
            $this->newLine();
            $this->info('🔄 TESTING UPDATED DATA');
            $this->info('======================');

            $test_publications = Publication::select('title', 'created_by', 'creator_name')
                ->limit(5)
                ->get();

            foreach ($test_publications as $publication) {
                $this->info("📰 {$publication->title}");
                $this->info("   👤 Created By: {$publication->creator_name} (ID: {$publication->created_by})");
                $this->newLine();
            }

            $this->info('✅ PUBLICATIONS DATA POPULATION COMPLETE!');
        } catch (\Exception $e) {
            $this->error('❌ ERROR: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
