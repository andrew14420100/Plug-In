import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import Icon from '../shared/Icon';
import { applyOrder, move, toggle } from '../shared/list';
import type {
	Candidate,
	Favorite,
	NavItem,
	Preferences,
} from '../shared/types';

interface CustomizeProps {
	prefs: Preferences;
	nav: NavItem[];
	candidates: Candidate[];
	icons: string[];
	allowHideWpMenu: boolean;
	onChange: ( partial: Partial< Preferences > ) => void;
	onReset: () => void;
	onClose: () => void;
}

export default function Customize( {
	prefs,
	nav,
	candidates,
	icons,
	allowHideWpMenu,
	onChange,
	onReset,
	onClose,
}: CustomizeProps ) {
	const available = candidates.filter(
		( c ) => ! prefs.favorites.some( ( f ) => f.id === c.id )
	);
	const [ newId, setNewId ] = useState< string >( available[ 0 ]?.id ?? '' );
	const [ newLabel, setNewLabel ] = useState( '' );
	const [ newIcon, setNewIcon ] = useState( 'star' );
	const navOrdered = applyOrder( nav, prefs.nav_order );
	const navIds = navOrdered.map( ( n ) => n.id );
	const favIds = prefs.favorites.map( ( f ) => f.id );
	const byId = new Map( candidates.map( ( c ) => [ c.id, c ] ) );
	const selectedId = available.some( ( c ) => c.id === newId )
		? newId
		: ( available[ 0 ]?.id ?? '' );

	const updateFavorites = ( next: Favorite[] ) =>
		onChange( { favorites: next } );

	return (
		<aside
			className="bc-card bc-customize"
			aria-labelledby="bc-customize-title"
		>
			<header className="bc-widget-header">
				<h3 id="bc-customize-title">
					{ __( 'Personalizza la tua dashboard', 'bracelli-club' ) }
				</h3>
				<button
					type="button"
					className="bc-icon-button"
					onClick={ onClose }
				>
					<Icon name="close" size={ 18 } />
					<span className="screen-reader-text">
						{ __( 'Chiudi', 'bracelli-club' ) }
					</span>
				</button>
			</header>
			<p className="bc-muted bc-small">
				{ __(
					'Trascina i widget o usa le frecce per riordinarli. Le scelte valgono solo per il tuo account.',
					'bracelli-club'
				) }
			</p>

			<div className="bc-customize-grid">
				<section>
					<h4>{ __( 'Menu laterale', 'bracelli-club' ) }</h4>
					<ul className="bc-list">
						{ navOrdered.map( ( item, index ) => {
							const hidden = prefs.hidden_nav.includes( item.id );
							const locked = item.id === 'dashboard';
							return (
								<li key={ item.id }>
									<Icon name={ item.icon } size={ 18 } />
									<span className="bc-grow">
										{ item.label }
									</span>
									<button
										type="button"
										className="bc-icon-button"
										disabled={ index === 0 }
										onClick={ () =>
											onChange( {
												nav_order: move(
													navIds,
													item.id,
													-1
												),
											} )
										}
									>
										<Icon name="up" size={ 16 } />
										<span className="screen-reader-text">
											{ __(
												'Sposta su',
												'bracelli-club'
											) }{ ' ' }
											— { item.label }
										</span>
									</button>
									<button
										type="button"
										className="bc-icon-button"
										disabled={
											index === navOrdered.length - 1
										}
										onClick={ () =>
											onChange( {
												nav_order: move(
													navIds,
													item.id,
													1
												),
											} )
										}
									>
										<Icon name="down" size={ 16 } />
										<span className="screen-reader-text">
											{ __(
												'Sposta giù',
												'bracelli-club'
											) }{ ' ' }
											— { item.label }
										</span>
									</button>
									<button
										type="button"
										className="bc-icon-button"
										disabled={ locked }
										aria-pressed={ ! hidden }
										onClick={ () =>
											onChange( {
												hidden_nav: toggle(
													prefs.hidden_nav,
													item.id,
													! hidden
												),
											} )
										}
									>
										<Icon
											name={ hidden ? 'eyeOff' : 'eye' }
											size={ 16 }
										/>
										<span className="screen-reader-text">
											{ hidden
												? __(
														'Mostra voce',
														'bracelli-club'
													)
												: __(
														'Nascondi voce',
														'bracelli-club'
													) }{ ' ' }
											— { item.label }
										</span>
									</button>
								</li>
							);
						} ) }
					</ul>
				</section>

				<section>
					<h4>{ __( 'Preferiti', 'bracelli-club' ) }</h4>
					{ prefs.favorites.length === 0 && (
						<p className="bc-muted bc-small">
							{ __( 'Nessun preferito.', 'bracelli-club' ) }
						</p>
					) }
					<ul className="bc-list">
						{ prefs.favorites.map( ( fav, index ) => (
							<li key={ fav.id }>
								<Icon name={ fav.icon } size={ 18 } />
								<span className="bc-grow">
									{ fav.label ||
										byId.get( fav.id )?.label ||
										fav.id }
								</span>
								<button
									type="button"
									className="bc-icon-button"
									disabled={ index === 0 }
									onClick={ () => {
										const order = move(
											favIds,
											fav.id,
											-1
										);
										updateFavorites(
											order.map( ( id ) =>
												prefs.favorites.find(
													( f ) => f.id === id
												)!
											)
										);
									} }
								>
									<Icon name="up" size={ 16 } />
									<span className="screen-reader-text">
										{ __( 'Sposta su', 'bracelli-club' ) }
									</span>
								</button>
								<button
									type="button"
									className="bc-icon-button"
									onClick={ () =>
										updateFavorites(
											prefs.favorites.filter(
												( f ) => f.id !== fav.id
											)
										)
									}
								>
									<Icon name="trash" size={ 16 } />
									<span className="screen-reader-text">
										{ __(
											'Rimuovi preferito',
											'bracelli-club'
										) }
									</span>
								</button>
							</li>
						) ) }
					</ul>
					{ available.length > 0 && prefs.favorites.length < 20 && (
						<form
							className="bc-fav-form"
							onSubmit={ ( event ) => {
								event.preventDefault();
								if ( ! selectedId ) {
									return;
								}
								updateFavorites( [
									...prefs.favorites,
									{
										id: selectedId,
										label: newLabel.trim(),
										icon: newIcon,
									},
								] );
								setNewLabel( '' );
							} }
						>
							<label htmlFor="bc-fav-page">
								{ __( 'Pagina', 'bracelli-club' ) }
							</label>
							<select
								id="bc-fav-page"
								value={ selectedId }
								onChange={ ( e ) => setNewId( e.target.value ) }
							>
								{ available.map( ( c ) => (
									<option key={ c.id } value={ c.id }>
										{ c.label }
									</option>
								) ) }
							</select>
							<label htmlFor="bc-fav-label">
								{ __(
									'Etichetta (facoltativa)',
									'bracelli-club'
								) }
							</label>
							<input
								id="bc-fav-label"
								type="text"
								maxLength={ 60 }
								value={ newLabel }
								onChange={ ( e ) =>
									setNewLabel( e.target.value )
								}
							/>
							<fieldset className="bc-icon-picker">
								<legend>
									{ __( 'Icona', 'bracelli-club' ) }
								</legend>
								{ icons.map( ( icon ) => (
									<label
										key={ icon }
										htmlFor={ `bc-fav-icon-${ icon }` }
										className={
											newIcon === icon ? 'is-active' : ''
										}
									>
										<input
											id={ `bc-fav-icon-${ icon }` }
											type="radio"
											name="bc-fav-icon"
											value={ icon }
											checked={ newIcon === icon }
											onChange={ () =>
												setNewIcon( icon )
											}
										/>
										<Icon name={ icon } size={ 18 } />
										<span className="screen-reader-text">
											{ icon }
										</span>
									</label>
								) ) }
							</fieldset>
							<button type="submit" className="bc-button">
								<Icon name="plus" size={ 18 } />
								{ __( 'Aggiungi', 'bracelli-club' ) }
							</button>
						</form>
					) }
				</section>

				<section>
					<h4>{ __( 'Opzioni', 'bracelli-club' ) }</h4>
					<label className="bc-switch" htmlFor="bc-login-dashboard">
						<input
							id="bc-login-dashboard"
							type="checkbox"
							checked={ prefs.login_to_dashboard }
							onChange={ ( e ) =>
								onChange( {
									login_to_dashboard: e.target.checked,
								} )
							}
						/>
						<span>
							{ __(
								'Apri questa dashboard dopo l’accesso',
								'bracelli-club'
							) }
						</span>
					</label>
					{ allowHideWpMenu && (
						<p className="bc-muted bc-small">
							{ __(
								'Il menu WordPress si nasconde dalla voce in fondo al menu laterale; il pulsante per riaprirlo resta sempre visibile.',
								'bracelli-club'
							) }
						</p>
					) }
					<button
						type="button"
						className="bc-button bc-button--ghost"
						onClick={ onReset }
					>
						<Icon name="refresh" size={ 18 } />
						{ __(
							'Ripristina preferenze predefinite',
							'bracelli-club'
						) }
					</button>
				</section>
			</div>
		</aside>
	);
}
