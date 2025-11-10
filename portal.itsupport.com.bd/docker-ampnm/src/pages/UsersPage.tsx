import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, User, Trash2, Edit, UserCog } from 'lucide-react';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { Skeleton } from '@/components/ui/skeleton';

interface User {
  id: string;
  username: string;
  role: 'admin' | 'viewer';
  created_at: string;
}

const UsersPage = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreatingUser, setIsCreatingUser] = useState(false);
  const [newUsername, setNewUsername] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newRole, setNewRole] = useState<'admin' | 'viewer'>('viewer');
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [isEditingRole, setIsEditingRole] = useState(false);

  const userRole = (window as any).userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const fetchUsers = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await fetch('api.php?action=get_users');
      const data = await response.json();
      if (response.ok && data) {
        setUsers(data);
      } else {
        showError(data.error || 'Failed to load users.');
      }
    } catch (error) {
      console.error('Failed to load users:', error);
      showError('Failed to load users.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isAdmin) {
      fetchUsers();
    }
  }, [fetchUsers, isAdmin]);

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isAdmin) {
      showError('You do not have permission to create users.');
      return;
    }
    setIsCreatingUser(true);
    const toastId = showLoading('Creating user...');
    try {
      const response = await fetch('api.php?action=create_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: newUsername, password: newPassword, role: newRole }),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        showSuccess(data.message);
        setNewUsername('');
        setNewPassword('');
        setNewRole('viewer');
        fetchUsers();
      } else {
        showError(data.error || 'Failed to create user.');
      }
    } catch (error) {
      console.error('Failed to create user:', error);
      showError('Failed to create user.');
    } finally {
      dismissToast(toastId);
      setIsCreatingUser(false);
    }
  };

  const handleDeleteUser = async (userId: string, username: string) => {
    if (!isAdmin) {
      showError('You do not have permission to delete users.');
      return;
    }
    if (username === 'admin') {
      showError('Cannot delete the default admin user.');
      return;
    }
    if (!window.confirm(`Are you sure you want to delete user "${username}"?`)) return;

    const toastId = showLoading('Deleting user...');
    try {
      const response = await fetch('api.php?action=delete_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId }),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        showSuccess(data.message);
        fetchUsers();
      } else {
        showError(data.error || 'Failed to delete user.');
      }
    } catch (error) {
      console.error('Failed to delete user:', error);
      showError('Failed to delete user.');
    } finally {
      dismissToast(toastId);
    }
  };

  const handleUpdateUserRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isAdmin) {
      showError('You do not have permission to update user roles.');
      return;
    }
    if (!editingUser) return;
    if (editingUser.username === 'admin') {
      showError('Cannot change the role of the default admin user.');
      return;
    }

    setIsEditingRole(true);
    const toastId = showLoading('Updating user role...');
    try {
      const response = await fetch('api.php?action=update_user_role', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: editingUser.id, role: editingUser.role }),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        showSuccess(data.message);
        setEditingUser(null);
        fetchUsers();
      } else {
        showError(data.error || 'Failed to update user role.');
      }
    } catch (error) {
      console.error('Failed to update user role:', error);
      showError('Failed to update user role.');
    } finally {
      dismissToast(toastId);
      setIsEditingRole(false);
    }
  };

  if (!isAdmin) {
    return (
      <div className="container mx-auto p-4 text-center text-red-400">
        <h1 className="text-3xl font-bold mb-4">Access Denied</h1>
        <p>You do not have permission to manage users.</p>
        <Link to="/" className="text-blue-400 hover:underline mt-4 block">Go to Dashboard</Link>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-3xl font-bold text-white mb-6">User Management</h1>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        {/* Create User Form */}
        <Card className="md:col-span-1">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Plus className="h-5 w-5" />
              Create New User
            </CardTitle>
            <CardDescription>Add new users to the application and assign their roles.</CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleCreateUser} className="space-y-4">
              <div>
                <Label htmlFor="new_username">Username</Label>
                <Input id="new_username" value={newUsername} onChange={e => setNewUsername(e.target.value)} required />
              </div>
              <div>
                <Label htmlFor="new_password">Password</Label>
                <Input id="new_password" type="password" value={newPassword} onChange={e => setNewPassword(e.target.value)} required />
              </div>
              <div>
                <Label htmlFor="new_role">Role</Label>
                <Select value={newRole} onValueChange={value => setNewRole(value as 'admin' | 'viewer')}>
                  <SelectTrigger id="new_role">
                    <SelectValue placeholder="Select role" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="viewer">Viewer</SelectItem>
                    <SelectItem value="admin">Admin</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button type="submit" className="w-full" disabled={isCreatingUser}>
                <Plus className={`h-4 w-4 mr-2 ${isCreatingUser ? 'animate-spin' : ''}`} />
                {isCreatingUser ? 'Creating...' : 'Create User'}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* User List */}
        <Card className="md:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <UserCog className="h-5 w-5" />
              Existing Users
            </CardTitle>
            <CardDescription>View and manage existing user accounts and their roles.</CardDescription>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="space-y-4">
                {[...Array(3)].map((_, i) => (
                  <Skeleton key={i} className="h-16 w-full rounded-lg" />
                ))}
              </div>
            ) : users.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <User className="h-12 w-12 mx-auto mb-4" />
                <p>No users found.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Username</TableHead>
                      <TableHead>Role</TableHead>
                      <TableHead>Created At</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {users.map(user => (
                      <TableRow key={user.id}>
                        <TableCell className="font-medium">{user.username}</TableCell>
                        <TableCell className="capitalize">{user.role}</TableCell>
                        <TableCell>{new Date(user.created_at).toLocaleString()}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end space-x-2">
                            {user.username !== 'admin' && (
                              <>
                                <Button variant="ghost" size="icon" onClick={() => setEditingUser(user)} title="Edit Role">
                                  <Edit className="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => handleDeleteUser(user.id, user.username)} title="Delete User">
                                  <Trash2 className="h-4 w-4 text-red-500" />
                                </Button>
                              </>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Edit Role Dialog */}
      {editingUser && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <Card className="w-full max-w-sm">
            <CardHeader>
              <CardTitle>Edit Role for {editingUser.username}</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleUpdateUserRole} className="space-y-4">
                <div>
                  <Label htmlFor="edit_role_select">Role</Label>
                  <Select value={editingUser.role} onValueChange={value => setEditingUser(prev => prev ? { ...prev, role: value as 'admin' | 'viewer' } : null)}>
                    <SelectTrigger id="edit_role_select">
                      <SelectValue placeholder="Select role" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="viewer">Viewer</SelectItem>
                      <SelectItem value="admin">Admin</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex justify-end gap-2">
                  <Button type="button" variant="outline" onClick={() => setEditingUser(null)}>Cancel</Button>
                  <Button type="submit" disabled={isEditingRole}>
                    <Save className={`h-4 w-4 mr-2 ${isEditingRole ? 'animate-spin' : ''}`} />
                    {isEditingRole ? 'Saving...' : 'Save Changes'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
};

export default UsersPage;