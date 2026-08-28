import ArticleDetailClient from './ArticleDetailClient';

export function generateStaticParams() {
    return [
        { slug: 'details' }
    ];
}

export default function ArticleDetailPage() {
    return <ArticleDetailClient />;
}
