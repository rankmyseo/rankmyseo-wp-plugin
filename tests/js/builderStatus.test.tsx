import { render, screen } from '@testing-library/react';
import { BuilderStatusList } from '../../src/admin/components/BuilderStatus';

const elementor = {
	id: 'elementor',
	label: 'Elementor',
	detected: true,
	active: true,
	version: '3.24.0',
};

const divi = {
	id: 'divi',
	label: 'Divi',
	detected: false,
	active: false,
	version: '',
};

describe( 'builder status', () => {
	it( 'shows active and not-detected builders', () => {
		render(
			<dl>
				<BuilderStatusList elementor={ elementor } divi={ divi } />
			</dl>
		);
		expect( screen.getByText( 'Elementor' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Active' ) ).toBeInTheDocument();
		expect( screen.getByText( '3.24.0' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Divi' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Not detected' ) ).toBeInTheDocument();
	} );
} );
