/**
 * ddCMS Theme - Main JavaScript
 * Modern interactions and functionality
 */

(function() {
    'use strict';
    
    // Mobile Menu Toggle
    const initMobileMenu = () => {
        const toggle = document.querySelector('.mobile-menu-toggle');
        const navigation = document.querySelector('.main-navigation');
        
        if (!toggle || !navigation) return;
        
        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            navigation.classList.toggle('active');
            
            // Animate hamburger icon
            toggle.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navigation.contains(e.target) && !toggle.contains(e.target)) {
                navigation.classList.remove('active');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.classList.remove('active');
            }
        });
    };
    
    // Header Scroll Effect
    const initHeaderScroll = () => {
        const header = document.querySelector('.site-header');
        if (!header) return;
        
        let lastScroll = 0;
        const scrollThreshold = 50;
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > scrollThreshold) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        }, { passive: true });
    };
    
    // Scroll Animation for Sections
    const initScrollAnimations = () => {
        if (!('IntersectionObserver' in window)) return;
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Observe sections
        const sections = document.querySelectorAll('.features-section, .stats-section, .callout-section, .feature-card, .stat-box');
        sections.forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(30px)';
            section.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            observer.observe(section);
        });
        
        // Add CSS for animate-in class
        const style = document.createElement('style');
        style.textContent = `
            .animate-in {
                opacity: 1 !important;
                transform: translateY(0) !important;
            }
        `;
        document.head.appendChild(style);
    };
    
    // Smooth Scrolling
    const initSmoothScroll = () => {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '') return;
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    };
    
    // Form Validation Enhancement
    const initFormValidation = () => {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    };
    
    // Lazy Loading Images
    const initLazyLoading = () => {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            img.classList.add('loaded');
                        }
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px'
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    };
    
    // Parallax Effect for Hero Section
    const initParallax = () => {
        const hero = document.querySelector('.hero-section');
        if (!hero) return;
        
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * 0.5;
            
            if (scrolled < hero.offsetHeight) {
                hero.style.transform = `translateY(${rate}px)`;
            }
        }, { passive: true });
    };
    
    // Initialize all functionality when DOM is ready
    const init = () => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                initMobileMenu();
                initHeaderScroll();
                initScrollAnimations();
                initSmoothScroll();
                initFormValidation();
                initLazyLoading();
                initParallax();
            });
        } else {
            initMobileMenu();
            initHeaderScroll();
            initScrollAnimations();
            initSmoothScroll();
            initFormValidation();
            initLazyLoading();
            initParallax();
        }
    };
    
    init();
})();

