export function BrandMark( { size = 28 }: { size?: number } ): JSX.Element {
	return (
		<svg
			width={ size }
			height={ size }
			viewBox="0 0 28 28"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			className="rms-brand-mark"
		>
			<rect width="28" height="28" rx="7" fill="#FF5C22" />
			<rect x="6" y="15" width="4" height="7" rx="1.2" fill="#FFFFFF" />
			<rect x="12" y="11" width="4" height="11" rx="1.2" fill="#FFFFFF" />
			<rect x="18" y="6" width="4" height="16" rx="1.2" fill="#FFFFFF" />
		</svg>
	);
}
