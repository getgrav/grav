/**
 * ddCMS Theme - Scroll Animations
 * Smooth, minimalist animations triggered on scroll
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        threshold: 0.15, // Percentage of element that must be visible
        rootMargin: '0px 0px -50px 0px', // Start animation slightly before element fully visible
    };

    // Intersection Observer for scroll animations
    const observerCallback = (entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                // Optional: stop observing after animation
                // observer.unobserve(entry.target);
            }
        });
    };

    const observer = new IntersectionObserver(observerCallback, config);

    // Initialize animations on DOM load
    const initAnimations = () => {
        // Observe all elements with animate-on-scroll class
        const animatedElements = document.querySelectorAll('.animate-on-scroll');
        animatedElements.forEach(element => {
            observer.observe(element);
        });

        // Auto-detect and animate feature cards
        const featureCards = document.querySelectorAll('.feature-card');
        featureCards.forEach((card, index) => {
            if (!card.classList.contains('animate-on-scroll')) {
                card.classList.add('animate-on-scroll', 'fade-in-up');
                observer.observe(card);
            }
        });

        // Auto-detect and animate stat boxes
        const statBoxes = document.querySelectorAll('.stat-box');
        statBoxes.forEach((box, index) => {
            if (!box.classList.contains('animate-on-scroll')) {
                box.classList.add('animate-on-scroll', 'scale-in');
                observer.observe(box);
            }
        });

        // Auto-detect and animate content sections
        const contentSections = document.querySelectorAll('.content-section, .text-section');
        contentSections.forEach((section, index) => {
            if (!section.classList.contains('animate-on-scroll')) {
                section.classList.add('animate-on-scroll', 'fade-in');
                observer.observe(section);
            }
        });

        // Auto-detect and animate image-text sections alternating
        const imageTextSections = document.querySelectorAll('.image-text-section');
        imageTextSections.forEach((section, index) => {
            if (!section.classList.contains('animate-on-scroll')) {
                const animClass = index % 2 === 0 ? 'slide-in-left' : 'slide-in-right';
                section.classList.add('animate-on-scroll', animClass);
                observer.observe(section);
            }
        });
    };

    // Smooth scroll for anchor links
    const initSmoothScroll = () => {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Skip if it's just "#"
                if (href === '#') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const offsetTop = target.offsetTop - 100; // Account for fixed header

                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    };

    // Add loading class to body, remove when fully loaded
    const initPageLoad = () => {
        document.body.classList.remove('loading');
    };

    // Parallax effect for hero sections (subtle)
    const initParallax = () => {
        const heroSections = document.querySelectorAll('.hero-section');

        if (heroSections.length > 0 && window.innerWidth > 768) {
            let ticking = false;

            const updateParallax = () => {
                const scrolled = window.pageYOffset;

                heroSections.forEach(hero => {
                    const heroTop = hero.offsetTop;
                    const heroHeight = hero.offsetHeight;

                    if (scrolled < heroTop + heroHeight) {
                        const yPos = -(scrolled - heroTop) * 0.3; // Parallax speed
                        hero.style.backgroundPositionY = `${yPos}px`;
                    }
                });

                ticking = false;
            };

            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(updateParallax);
                    ticking = true;
                }
            });
        }
    };

    // Counter animation for stat numbers
    const initCounters = () => {
        const counters = document.querySelectorAll('.stat-number');

        counters.forEach(counter => {
            const target = counter.textContent.trim();
            const hasPlus = target.includes('+');
            const hasPercent = target.includes('%');
            const hasK = target.includes('k') || target.includes('K');
            const hasM = target.includes('m') || target.includes('M');

            // Extract just the number
            const numericValue = parseFloat(target.replace(/[^0-9.]/g, ''));

            if (!isNaN(numericValue)) {
                counter.setAttribute('data-target', numericValue);
                counter.setAttribute('data-suffix', hasPlus ? '+' : hasPercent ? '%' : hasK ? 'k' : hasM ? 'M' : '');
                counter.textContent = '0';

                // Observe for animation trigger
                const counterObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting && !counter.classList.contains('counted')) {
                            animateCounter(counter);
                            counter.classList.add('counted');
                            counterObserver.unobserve(counter);
                        }
                    });
                }, { threshold: 0.5 });

                counterObserver.observe(counter);
            }
        });
    };

    const animateCounter = (counter) => {
        const target = parseFloat(counter.getAttribute('data-target'));
        const suffix = counter.getAttribute('data-suffix');
        const duration = 2000; // 2 seconds
        const steps = 60;
        const increment = target / steps;
        let current = 0;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = Math.round(target) + suffix;
                clearInterval(timer);
            } else {
                counter.textContent = Math.round(current) + suffix;
            }
        }, duration / steps);
    };

    // Initialize everything when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initAnimations();
            initSmoothScroll();
            initPageLoad();
            initParallax();
            initCounters();
        });
    } else {
        initAnimations();
        initSmoothScroll();
        initPageLoad();
        initParallax();
        initCounters();
    }

})();
