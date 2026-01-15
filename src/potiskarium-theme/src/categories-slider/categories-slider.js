export const EASING_FLAT = (x) => x;
export const EASING_EXPONENTIAL_IN = (x, e = 2) => Math.pow(x, e);
export const EASING_EXPONENTIAL_IN_OUT = (x, e = 2) => (x <= 0.5) ? Math.pow(x * 2, e) / 2 : 1 - Math.pow(2 - x * 2, e) / 2;
export const EASING_QUAD_IN = EASING_EXPONENTIAL_IN;
export const EASING_QUAD_IN_OUT = EASING_EXPONENTIAL_IN_OUT;
export const EASING_CUBIC_IN = (x) => EASING_EXPONENTIAL_IN(x, 3);
export const EASING_CUBIC_IN_OUT = (x) => EASING_EXPONENTIAL_IN_OUT(x, 3);
export const EASING_SPHERE_IN = (v) => 1 - Math.sqrt(1 - Math.pow(v, 2));
export const EASING_SPHERE_OUT = (v) => Math.sqrt(1 - Math.pow(1 - v, 2));
export const EASING_SIN_OUT = (x) => Math.sin(x * Math.PI * 0.5)

class CategoriesSlider {

	transitionMs = 400;

	element;

	view;

	content;

	viewWidth;

	itemWidth;

	itemsCount;

	itemsInView = 2;

	offset = 0;

	animationStart = 0;

	animationEnd = 0;

	animationStarted = null;

	touchStartTime = null;

	touchStartX = null;

	touchStartY = null;

	constructor(element) {
		this.element = element;
		this.view = element.querySelector('.product-categories-slider-view');
		this.content = element.querySelector('.product-categories-slider-content');
		this.itemsCount = this.getItems().length;

		const leftArrow = this.element.querySelector('.slider-left-arrow');
		leftArrow.addEventListener('click', () => this.slideLeft());
		const rightArrow = this.element.querySelector('.slider-right-arrow');
		rightArrow.addEventListener('click', () => this.slideRight());

		this.content.addEventListener(
			'mousedown',
			(event) => {
				this.touchStartTime = performance.now();
				this.touchStartX = event.clientX;
				this.touchStartY = event.clientY;
			},
			false
		);

		this.content.addEventListener(
			'mouseup',
			(event) => {
				const touchendX = event.clientX;
				const touchendY = event.clientY;
				this.handleGesture(touchendX, touchendY, event);
			},
			false
		);

		this.content.addEventListener(
			'touchstart',
			(event) => {
				this.touchStartTime = performance.now();
				this.touchStartX = event.changedTouches[0].screenX;
				this.touchStartY = event.changedTouches[0].screenY;
			},
			false
		);

		this.content.addEventListener(
			'touchend',
			(event) => {
				const touchendX = event.changedTouches[0].screenX;
				const touchendY = event.changedTouches[0].screenY;
				this.handleGesture(touchendX, touchendY, event);
			},
			false
		);

		window.addEventListener('resize', () => this.initSizes());
		this.initSizes();
	}

	isAnimating() {
		return this.animationStarted !== null;
	}

	isScrollable() {
		return this.itemsCount > this.itemsInView;
	}

	getItems() {
		return this.content.querySelectorAll('.product-category-item');
	}

	addClass(element, css) {
		if (Array.isArray((css)) && css.length > 0) {
			css.forEach((cls) => {
				if (typeof cls === 'string' && cls.length > 0) element.classList.add(cls);
			});
		} else if (css) {
			css.split(' ').forEach((cls) => {
				if (typeof cls === 'string' && cls.length > 0) element.classList.add(cls);
			});
		}
	}

	removeClass(element, css) {
		element.classList.remove(css);
	}

	hasClass(element, css) {
		return element.classList.contains(css);
	}

	toggleClass(element, css, classActive = null) {
		const hasClass = this.hasClass(element, css);
		if (hasClass && classActive !== true) {
			this.removeClass(element, css);
		} else if (classActive !== false && !hasClass) {
			this.addClass(element, css);
		}
	}

	initSizes() {
		this.viewWidth = this.view.offsetWidth;

		if (this.viewWidth > 1000) {
			this.itemsInView = 3;
		} else if (this.viewWidth > 768) {
			this.itemsInView = 2;
		} else {
			this.itemsInView = 1;
		}

		this.toggleClass(this.element.querySelector('.slider-left-arrow'), 'hidden', !this.isScrollable());
		this.toggleClass(this.element.querySelector('.slider-right-arrow'), 'hidden', !this.isScrollable());

		this.viewWidth = this.view.offsetWidth;
		this.itemWidth = this.viewWidth / this.itemsInView;

		this.content.style.width = (this.itemsCount * this.itemWidth) + 'px';

		const items = this.content.querySelectorAll('.product-category-item');
		items.forEach((item) => item.style.width = `${this.itemWidth}px`);
	}

	prepend() {
		const items = this.getItems();
		const first = items[0];
		const last = items[items.length - 1];
		this.content.removeChild(last);
		this.content.insertBefore(last, first);
	}

	append() {
		const items = this.getItems();
		const first = items[0];
		this.content.removeChild(first);
		this.content.appendChild(first);
	}

	handleGesture(touchEndX, touchEndY, e) {
		const time = performance.now() - this.touchStartTime;
		this.touchStartTime = null;

		if (time > 1000) return;

		const dist = touchEndX - this.touchStartX;

		if (Math.abs(dist) < 100) return;

		if (dist < 0) {
			this.slideRight();
			e.preventDefault();
			e.stopPropagation();
		}

		if (dist > 0) {
			this.slideLeft();
			e.preventDefault();
			e.stopPropagation();
		}
	}

	animate() {
		const time = performance.now();
		if (this.animationStarted === null) {
			this.animationStarted = time;
		}
		const elapsed = time - this.animationStarted;
		const progress = EASING_QUAD_IN_OUT(elapsed / this.transitionMs);

		this.offset = this.animationStart + (progress * (this.animationEnd - this.animationStart));

		if (elapsed < this.transitionMs) {
			requestAnimationFrame(() => this.animate());
		} else {
			this.animationStarted = null;
			this.offset = this.animationEnd;
			if (this.offset < 0) {
				this.append();
				this.offset = 0;
			}
		}

		this.content.style.marginLeft = `${this.offset}px`;
	}

	startAnimation(start, end) {
		this.animationStart = start;
		this.animationEnd = end;
		this.animationStarted = null;
		this.animate();
	}

	slideLeft() {
		if (this.isAnimating() || !this.isScrollable()) return;
		this.prepend();
		this.startAnimation(- this.itemWidth, 0);
	}

	slideRight() {
		if (this.isAnimating() || !this.isScrollable()) return;
		this.startAnimation(0, - this.itemWidth);
	}

}

document.addEventListener(
	"DOMContentLoaded",
	function() {
		const elements = document.querySelectorAll('.product-categories-slider');
		elements.forEach(element => new CategoriesSlider(element));
	}
);
