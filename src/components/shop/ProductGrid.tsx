import { ProductCard, type ProductCardData } from '@/components/shop/ProductCard';

export function ProductGrid({ products }: { products: ProductCardData[] }) {
  if (products.length === 0) {
    return (
      <p className="py-16 text-center text-ink-soft">
        Aucun produit dans cette catégorie pour le moment.
      </p>
    );
  }

  return (
    <ul className="grid grid-cols-2 gap-x-5 gap-y-10 lg:grid-cols-4">
      {products.map((product, index) => (
        <li key={product.id}>
          <ProductCard product={product} priority={index < 4} />
        </li>
      ))}
    </ul>
  );
}
