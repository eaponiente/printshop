import { Badge } from '@/components/ui/badge';
import type { HolidayBranch } from '../list';

/**
 * Renders a holiday's branch scope: an explicit "Nationwide" badge when the
 * holiday applies to every branch (empty array), otherwise up to two branch
 * badges followed by a "+N" overflow badge.
 */
export function BranchesCell({ branches }: { branches: HolidayBranch[] }) {
    if (branches.length === 0) {
        return (
            <Badge variant="outline" className="text-muted-foreground">
                Nationwide
            </Badge>
        );
    }

    const visible = branches.slice(0, 2);
    const overflow = branches.length - visible.length;

    return (
        <div className="flex flex-wrap gap-1">
            {visible.map((branch) => (
                <Badge key={branch.id} variant="secondary">
                    {branch.name}
                </Badge>
            ))}
            {overflow > 0 && <Badge variant="secondary">+{overflow}</Badge>}
        </div>
    );
}
