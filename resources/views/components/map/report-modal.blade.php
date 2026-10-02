<div
    x-show="reportModalOpen"
    x-cloak
    class="dlg-backdrop"
    @click.self="closeReportModal()"
    @keydown.escape.window="closeReportModal()"
>
    <div class="dlg-card map-scroll max-w-lg" role="dialog" aria-modal="true" aria-label="What's the Issue?">
        <button type="button" class="icon-btn absolute right-4 top-4 z-10" @click="closeReportModal()" aria-label="Close report modal">
            <i class="ph ph-x"></i>
        </button>

        <form @submit.prevent="submitReport()" class="p-5 sm:p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <i class="ph ph-warning-circle text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-100">What's the Issue?</h2>
                    <p class="text-xs text-zinc-400">
                        Help keep Bangued Atlas accurate for <span class="font-semibold text-zinc-200" x-text="reportPlaceName || 'this place'"></span>
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Select the issue</label>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label
                            class="flex items-center gap-2.5 rounded-xl border p-3 cursor-pointer transition-all duration-150"
                            :class="reportIssueType === 'wrong_info' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-zinc-900/50 text-zinc-300 hover:border-white/20 hover:bg-zinc-800/40'">
                            <input type="radio" name="issue_type" value="wrong_info" x-model="reportIssueType" class="sr-only">
                            <i class="ph ph-info text-base shrink-0"></i>
                            <div class="text-xs font-medium">Wrong information</div>
                        </label>

                        <label
                            class="flex items-center gap-2.5 rounded-xl border p-3 cursor-pointer transition-all duration-150"
                            :class="reportIssueType === 'closed_permanently' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-zinc-900/50 text-zinc-300 hover:border-white/20 hover:bg-zinc-800/40'">
                            <input type="radio" name="issue_type" value="closed_permanently" x-model="reportIssueType" class="sr-only">
                            <i class="ph ph-storefront text-base shrink-0"></i>
                            <div class="text-xs font-medium">Permanently closed</div>
                        </label>

                        <label
                            class="flex items-center gap-2.5 rounded-xl border p-3 cursor-pointer transition-all duration-150"
                            :class="reportIssueType === 'wrong_location' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-zinc-900/50 text-zinc-300 hover:border-white/20 hover:bg-zinc-800/40'">
                            <input type="radio" name="issue_type" value="wrong_location" x-model="reportIssueType" class="sr-only">
                            <i class="ph ph-map-pin text-base shrink-0"></i>
                            <div class="text-xs font-medium">Wrong pin location</div>
                        </label>

                        <label
                            class="flex items-center gap-2.5 rounded-xl border p-3 cursor-pointer transition-all duration-150"
                            :class="reportIssueType === 'duplicate' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-zinc-900/50 text-zinc-300 hover:border-white/20 hover:bg-zinc-800/40'">
                            <input type="radio" name="issue_type" value="duplicate" x-model="reportIssueType" class="sr-only">
                            <i class="ph ph-copy text-base shrink-0"></i>
                            <div class="text-xs font-medium">Duplicate place</div>
                        </label>

                        <label
                            class="flex items-center gap-2.5 rounded-xl border p-3 cursor-pointer transition-all duration-150"
                            :class="reportIssueType === 'inappropriate' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-zinc-900/50 text-zinc-300 hover:border-white/20 hover:bg-zinc-800/40'">
                            <input type="radio" name="issue_type" value="inappropriate" x-model="reportIssueType" class="sr-only">
                            <i class="ph ph-warning-octagon text-base shrink-0"></i>
                            <div class="text-xs font-medium">Inappropriate content</div>
                        </label>

                        <label
                            class="flex items-center gap-2.5 rounded-xl border p-3 cursor-pointer transition-all duration-150"
                            :class="reportIssueType === 'other' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-zinc-900/50 text-zinc-300 hover:border-white/20 hover:bg-zinc-800/40'">
                            <input type="radio" name="issue_type" value="other" x-model="reportIssueType" class="sr-only">
                            <i class="ph ph-dots-three-circle text-base shrink-0"></i>
                            <div class="text-xs font-medium">Other feedback</div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5" for="report-details">
                        Correction or additional details <span class="normal-case font-normal text-zinc-500">(optional)</span>
                    </label>
                    <textarea
                        id="report-details"
                        x-model="reportDetails"
                        rows="3"
                        maxlength="1000"
                        placeholder="Provide details about what needs to be fixed or the correct information..."
                        class="w-full rounded-xl border border-white/10 bg-zinc-900/80 px-3.5 py-2.5 text-xs text-zinc-100 placeholder-zinc-500 focus:border-emerald-500/60 focus:outline-none focus:ring-1 focus:ring-emerald-500/60"
                    ></textarea>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button
                    type="button"
                    class="btn btn-ghost !py-2 text-xs"
                    @click="closeReportModal()"
                    :disabled="reportSubmitting">
                    Cancel
                </button>
                <button
                    type="submit"
                    class="btn btn-accent !py-2 text-xs font-semibold"
                    :disabled="reportSubmitting">
                    <span x-show="!reportSubmitting" class="flex items-center gap-1.5">
                        <i class="ph ph-paper-plane-tilt"></i> Submit Report
                    </span>
                    <span x-show="reportSubmitting" class="flex items-center gap-1.5">
                        <i class="ph ph-spinner animate-spin"></i> Submitting...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
