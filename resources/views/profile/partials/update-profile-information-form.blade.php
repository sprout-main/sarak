<section x-data="avatarCropper()">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile photo, identity, and email address.") }}
        </p>
    </header>

    <!-- Avatar Upload & Crop Section -->
    <div class="mt-6 border-b border-gray-200 pb-6">
        <label class="block text-sm font-medium text-gray-700 mb-3">{{ __('Profile Picture') }}</label>
        
        <div class="flex flex-col sm:flex-row items-center gap-5">
            <div class="relative shrink-0">
                @if ($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover ring-2 ring-emerald-500/40 shadow-sm" />
                @else
                    <div class="h-20 w-20 rounded-full bg-emerald-500/15 border-2 border-emerald-500/30 flex items-center justify-center text-2xl font-bold text-emerald-600 shadow-sm">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="space-y-2 text-center sm:text-left">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <input
                        type="file"
                        x-ref="fileInput"
                        class="hidden"
                        accept="image/png,image/jpeg,image/webp,image/gif"
                        @change="onFileSelected($event)"
                    />
                    <button
                        type="button"
                        @click="$refs.fileInput.click()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-colors cursor-pointer"
                    >
                        <i class="ph ph-camera text-sm"></i>
                        {{ __('Upload Photo') }}
                    </button>

                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="inline">
                            @csrf
                            @method('delete')
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer"
                            >
                                <i class="ph ph-trash text-sm"></i>
                                {{ __('Remove') }}
                            </button>
                        </form>
                    @endif
                </div>
                <p class="text-xs text-gray-500">
                    JPG, PNG, or WEBP up to 5MB. Interactive crop and zoom available.
                </p>

                @if (session('status') === 'avatar-updated')
                    <p class="text-xs font-medium text-emerald-600">{{ __('Profile picture updated successfully.') }}</p>
                @elseif (session('status') === 'avatar-deleted')
                    <p class="text-xs font-medium text-gray-600">{{ __('Profile picture removed.') }}</p>
                @endif
                <x-input-error class="text-xs" :messages="$errors->get('avatar')" />
            </div>
        </div>

        <!-- Hidden Avatar Form for Cropped Data Submission -->
        <form x-ref="avatarForm" method="POST" action="{{ route('profile.avatar.update') }}" class="hidden">
            @csrf
            <input type="hidden" name="avatar_data" x-model="avatarData" />
        </form>

        <!-- Crop & Zoom Modal -->
        <div
            x-show="modalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
            @keydown.escape.window="modalOpen = false"
        >
            <div
                @click.outside="modalOpen = false"
                class="relative w-full max-w-sm rounded-2xl bg-zinc-900 border border-zinc-700/80 p-5 text-zinc-100 shadow-2xl space-y-4"
            >
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="ph ph-crop text-emerald-400 text-base"></i> Crop & Zoom Avatar
                    </h3>
                    <button type="button" @click="modalOpen = false" class="text-zinc-400 hover:text-white transition-colors">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                <div class="flex flex-col items-center">
                    <canvas
                        x-ref="canvas"
                        width="300"
                        height="300"
                        class="rounded-xl bg-zinc-950 cursor-grab active:cursor-grabbing border border-zinc-800 touch-none shadow-inner"
                        @mousedown="startDrag($event)"
                        @mousemove="onDrag($event)"
                        @mouseup="stopDrag()"
                        @mouseleave="stopDrag()"
                        @touchstart="startDrag($event)"
                        @touchmove="onDrag($event)"
                        @touchend="stopDrag()"
                        @wheel="onWheel($event)"
                    ></canvas>
                    <p class="text-[11px] text-zinc-400 mt-2">
                        Drag inside circle to position • Scroll or use slider to zoom
                    </p>
                </div>

                <!-- Zoom Slider -->
                <div class="space-y-1.5 px-1">
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="flex items-center gap-1"><i class="ph ph-magnifying-glass-minus"></i> Zoom</span>
                        <span class="font-mono text-[11px]" x-text="Math.round(zoom * 100) + '%'"></span>
                    </div>
                    <input
                        type="range"
                        x-model.number="zoom"
                        :min="minZoom"
                        :max="maxZoom"
                        step="0.02"
                        @input="onZoomChange()"
                        class="w-full accent-emerald-400 h-1.5 bg-zinc-800 rounded-lg cursor-pointer"
                    />
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                    <button
                        type="button"
                        @click="modalOpen = false"
                        class="px-3.5 py-1.5 text-xs font-medium text-zinc-300 hover:text-white rounded-lg hover:bg-zinc-800 transition-colors"
                        :disabled="submitting"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveCrop()"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold bg-emerald-500 hover:bg-emerald-400 text-zinc-950 rounded-lg transition-colors shadow-md disabled:opacity-50"
                        :disabled="submitting"
                    >
                        <i x-show="!submitting" class="ph ph-check font-bold"></i>
                        <span x-show="submitting" class="inline-block h-3.5 w-3.5 rounded-full border-2 border-zinc-950 border-t-transparent animate-spin"></span>
                        <span x-text="submitting ? 'Saving...' : 'Apply Photo'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        @if (session('status') === 'profile-updated')
            <div class="flex items-center gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-300">
                <i class="ph ph-check-circle text-base text-emerald-400"></i>
                <span class="font-medium">Success: Profile information updated successfully.</span>
            </div>
        @endif

        @if ($errors->updateProfileInformation->any() || ($errors->any() && ! $errors->updatePassword->any() && ! $errors->userDeletion->any()))
            <div class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs text-rose-300">
                <i class="ph ph-warning-circle text-base text-rose-400 shrink-0 mt-0.5"></i>
                <div>
                    <div class="font-semibold">Unable to update profile. Please correct the errors below:</div>
                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-rose-200">
                        @foreach ($errors->updateProfileInformation->all() ?: $errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div>
            <x-input-label for="name" :value="__('Username')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="username" placeholder="e.g. mariasantos" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

    <script>
        function avatarCropper() {
            return {
                modalOpen: false,
                imgSrc: null,
                img: null,
                zoom: 1,
                minZoom: 1,
                maxZoom: 3,
                panX: 0,
                panY: 0,
                isDragging: false,
                dragStartX: 0,
                dragStartY: 0,
                initialPanX: 0,
                initialPanY: 0,
                submitting: false,
                avatarData: '',

                onFileSelected(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        this.imgSrc = event.target.result;
                        const image = new Image();
                        image.onload = () => {
                            this.img = image;
                            this.resetCrop();
                            this.modalOpen = true;
                            this.$nextTick(() => this.draw());
                        };
                        image.src = this.imgSrc;
                    };
                    reader.readAsDataURL(file);
                },

                resetCrop() {
                    this.zoom = 1;
                    this.panX = 0;
                    this.panY = 0;
                },

                startDrag(e) {
                    this.isDragging = true;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    this.dragStartX = clientX;
                    this.dragStartY = clientY;
                    this.initialPanX = this.panX;
                    this.initialPanY = this.panY;
                },

                onDrag(e) {
                    if (!this.isDragging) return;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    this.panX = this.initialPanX + (clientX - this.dragStartX);
                    this.panY = this.initialPanY + (clientY - this.dragStartY);
                    this.draw();
                },

                stopDrag() {
                    this.isDragging = false;
                },

                onWheel(e) {
                    e.preventDefault();
                    const delta = e.deltaY < 0 ? 0.08 : -0.08;
                    this.zoom = Math.min(this.maxZoom, Math.max(this.minZoom, parseFloat((this.zoom + delta).toFixed(2))));
                    this.draw();
                },

                onZoomChange() {
                    this.draw();
                },

                draw() {
                    const canvas = this.$refs.canvas;
                    if (!canvas || !this.img) return;
                    const ctx = canvas.getContext('2d');
                    const size = canvas.width;

                    ctx.clearRect(0, 0, size, size);

                    const imgAspect = this.img.width / this.img.height;
                    let drawW, drawH;
                    if (imgAspect > 1) {
                        drawH = size * this.zoom;
                        drawW = drawH * imgAspect;
                    } else {
                        drawW = size * this.zoom;
                        drawH = drawW / imgAspect;
                    }

                    const drawX = (size - drawW) / 2 + this.panX;
                    const drawY = (size - drawH) / 2 + this.panY;

                    ctx.save();
                    ctx.drawImage(this.img, drawX, drawY, drawW, drawH);
                    ctx.restore();

                    // Mask overlay with clear circular viewport
                    ctx.save();
                    ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
                    ctx.beginPath();
                    ctx.rect(0, 0, size, size);
                    ctx.arc(size / 2, size / 2, size / 2 - 10, 0, Math.PI * 2, true);
                    ctx.fill();

                    // Circular green guide border
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = '#4ade80';
                    ctx.beginPath();
                    ctx.arc(size / 2, size / 2, size / 2 - 10, 0, Math.PI * 2);
                    ctx.stroke();
                    ctx.restore();
                },

                saveCrop() {
                    if (!this.img) return;
                    this.submitting = true;

                    const size = 400;
                    const offscreen = document.createElement('canvas');
                    offscreen.width = size;
                    offscreen.height = size;
                    const ctx = offscreen.getContext('2d');

                    const canvasSize = this.$refs.canvas.width;
                    const cropRadius = canvasSize / 2 - 10;
                    const scaleFactor = (size / 2) / cropRadius;

                    const imgAspect = this.img.width / this.img.height;
                    let drawW, drawH;
                    if (imgAspect > 1) {
                        drawH = canvasSize * this.zoom;
                        drawW = drawH * imgAspect;
                    } else {
                        drawW = canvasSize * this.zoom;
                        drawH = drawW / imgAspect;
                    }

                    const drawX = (canvasSize - drawW) / 2 + this.panX;
                    const drawY = (canvasSize - drawH) / 2 + this.panY;

                    const relCenterX = (canvasSize / 2) - drawX;
                    const relCenterY = (canvasSize / 2) - drawY;

                    const destX = (size / 2) - (relCenterX * scaleFactor);
                    const destY = (size / 2) - (relCenterY * scaleFactor);
                    const destW = drawW * scaleFactor;
                    const destH = drawH * scaleFactor;

                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
                    ctx.clip();
                    ctx.drawImage(this.img, destX, destY, destW, destH);
                    ctx.restore();

                    this.avatarData = offscreen.toDataURL('image/png');
                    this.$nextTick(() => {
                        this.$refs.avatarForm.submit();
                    });
                }
            };
        }
    </script>
</section>
