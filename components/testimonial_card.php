<?php
// testimonial_card.php - Modular testimonial card component
$testimonial = $testimonial ?? [
    'review_id' => 0,
    'avatar_initial' => 'J',
    'stars' => 5,
    'name' => 'John Doe',
    'package' => 'Bali Adventure Package',
    'dates' => 'Jan 15 - Jan 22, 2024',
    'quote' => 'Amazing experience! The tour was perfectly organized and the guides were fantastic.',
    'client_profile_photo' => null,
    'photo_path' => null,
    'date' => date('Y-m-d'),
    'displayinHomePage' => 0
];
?>

<div 
  @click="selectedTestimonial = <?= htmlspecialchars(json_encode([
    'review_id' => $testimonial['review_id'],
    'avatar_initial' => $testimonial['avatar_initial'],
    'stars' => $testimonial['stars'],
    'name' => $testimonial['name'],
    'package' => $testimonial['package'],
    'dates' => $testimonial['dates'],
    'quote' => $testimonial['quote'],
    'client_profile_photo' => !empty($testimonial['client_profile_photo']) ? '../uploads/client_profiles/' . rawurlencode($testimonial['client_profile_photo']) : null,
    'photo_path' => !empty($testimonial['photo_path']) ? '../' . htmlspecialchars($testimonial['photo_path']) : null,
    'date' => $testimonial['date'],
    'displayinHomePage' => $testimonial['displayinHomePage']
  ])) ?>"
  class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-all duration-300 hover:scale-105 hover:-translate-y-1 cursor-pointer border border-gray-100 group">
    <!-- Trip Photo -->
    <?php if (!empty($testimonial['photo_path'])): ?>
        <div class="mb-3 -mx-4 -mt-4">
            <img src="../<?= htmlspecialchars($testimonial['photo_path']) ?>" alt="Trip Photo" class="w-full h-32 object-cover rounded-t-lg" />
        </div>
    <?php endif; ?>

    <div class="flex items-start space-x-3">
        <!-- Client Avatar -->
        <?php if (!empty($testimonial['client_profile_photo'])): ?>
            <img src="../uploads/client_profiles/<?= rawurlencode($testimonial['client_profile_photo']) ?>" alt="Client Photo" class="w-10 h-10 rounded-full object-cover flex-shrink-0" />
        <?php else: ?>
            <div class="w-10 h-10 bg-sky-500 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                <?= htmlspecialchars($testimonial['avatar_initial']) ?>
            </div>
        <?php endif; ?>
        <!-- Testimonial Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center mb-1">
                <!-- Stars -->
                <div class="flex text-yellow-400 text-sm">
                    <?php for ($i = 0; $i < $testimonial['stars']; $i++): ?>
                        ★
                    <?php endfor; ?>
                </div>
                <!-- Display Status Pill -->
                <div class="ml-2">
                    <?php if ($testimonial['displayinHomePage'] == 1): ?>
                        <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700 border border-emerald-300">Public</span>
                    <?php else: ?>
                        <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 border border-amber-300">Hidden</span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-gray-800 font-semibold text-sm truncate"><?= htmlspecialchars($testimonial['name']) ?></p>
            <p class="text-gray-600 text-xs truncate"><?= htmlspecialchars($testimonial['package']) ?></p>
            <p class="text-gray-500 text-xs truncate">Travel: <?= htmlspecialchars($testimonial['dates']) ?></p>
            <p class="text-gray-700 text-xs italic mt-2 line-clamp-2 hover:line-clamp-none transition-all duration-300" title="<?= htmlspecialchars($testimonial['quote']) ?>">
                "<?= htmlspecialchars($testimonial['quote']) ?>"
            </p>
        </div>
    </div>
</div>