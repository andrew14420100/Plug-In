import { __ } from '@wordpress/i18n';
import Icon from '../../shared/Icon';
import type { Candidate, Favorite } from '../../shared/types';

interface FavoritesProps {
	favorites: Favorite[];
	candidates: Candidate[];
	onCustomize: () => void;
}

export default function Favorites( {
	favorites,
	candidates,
	onCustomize,
}: FavoritesProps ) {
	const byId = new Map( candidates.map( ( c ) => [ c.id, c ] ) );
	const items = favorites
		.map( ( f ) => ( { favorite: f, candidate: byId.get( f.id ) } ) )
		.filter( ( entry ) => entry.candidate !== undefined );

	if ( items.length === 0 ) {
		return (
			<div className="bc-empty">
				<p>{ __( 'Nessun preferito ancora.', 'bracelli-club' ) }</p>
				<button
					type="button"
					className="bc-button bc-button--ghost"
					onClick={ onCustomize }
				>
					<Icon name="plus" size={ 18 } />
					{ __( 'Aggiungi preferiti', 'bracelli-club' ) }
				</button>
			</div>
		);
	}
	return (
		<ul className="bc-tiles">
			{ items.map( ( { favorite, candidate } ) => (
				<li key={ favorite.id }>
					<a className="bc-tile" href={ candidate!.url }>
						<Icon name={ favorite.icon } size={ 22 } />
						<span>{ favorite.label || candidate!.label }</span>
					</a>
				</li>
			) ) }
		</ul>
	);
}
